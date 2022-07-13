<?php

namespace Coil\EmailDelivery;

use DateTime;

use SS\Models\Automation\AutomationWorkflow_model;
use Coil\CompanyProfile\Service\CompanyService;
use Coil\Email\Service\EmailDomainBlacklist;
use Coil\EmailAccountManagement\CompanyEmailAccount;
use Coil\EmailAccountManagement\CompanyEmailAccountDao;
use Coil\EmailAccountManagement\Service\ProviderFactory;
use Coil\EmailAccountManagement\VerifiedDomain;
use Coil\EmailAccountManagement\VerifiedDomainDao;
use Coil\EmailDelivery\Recipients\RecipientGenerator;
use Coil\EmailDelivery\Service\UserSmtpConfigurationService;
use MY_Model;
use SS\Libraries\Billing\CombinedBilling;
use SS\Libraries\LoadHelper;
use SS\Libraries\LoadLibrary;
use SS\Libraries\LoadModel;
use SS\Libraries\Offering;
use SS\Models\CompanyProfile_model;
use SS\Models\Email\Email;
use SS\Models\Email\StandardEmail;
use SS\Models\EmailAddressCompany;
use SS\Libraries\SSLog;

/**
 * EmailDelivery Library
 *
 * Uses the mailship mailer library to deliver emails and text messages to lists,
 * leads, and users. Also used for notifications
 */
class EmailDeliveryHelper
{
    /* @var AutomationWorkflow_model */
    private $automationworkflow_model;

    /* @var CompanyProfile_model */
    private $companyprofile_model;

    /* @var CompanyEmailAccountDao */
    private $companyEmailAccountDao;

    /* @var VerifiedDomainDao */
    private $verifiedDomainDao;

    /* @var EmailPreprocessor */
    private $emailpreprocessor;

    /* @var ProviderFactory */
    private $providerFactory;

    /* @var EmailDomainBlacklist */
    private $emailDomainBlacklist;

    /* @var CombinedBilling */
    private $combinedBilling;

    /* @var ExternalSmtpQuotaEnforcer */
    private $quotaEnforcer;

    /* @var UserSmtpConfigurationService */
    private $smtpConfigService;

    const PA_COMPANY_ID = 308479174; // Perfect Audience ID

    public function __construct(
        AutomationWorkflow_model $automationWorkflowModel = null,
        CompanyProfile_model $companyProfileModel = null,
        $billingClientPlanModel = null,
        CompanyEmailAccountDao $companyEmailAccountDao = null,
        VerifiedDomainDao $verifiedDomainDao = null,
        EmailPreprocessor $emailPreprocessor = null,
        ProviderFactory $providerFactory = null,
        EmailDomainBlacklist $emailDomainBlacklist = null,
        CombinedBilling $combinedBilling = null,
        ExternalSmtpQuotaEnforcer $quotaEnforcer = null,
        UserSmtpConfigurationService $smtpConfigService = null
    ) {
        LoadHelper::encode();
        LoadHelper::utils();

        $this->automationworkflow_model = $automationWorkflowModel ?: LoadModel::automationWorkflow();
        $this->companyprofile_model = $companyProfileModel ?: LoadModel::companyProfile();
        $this->companyEmailAccountDao = $companyEmailAccountDao ?: LoadModel::companyEmailAccountDao();
        $this->verifiedDomainDao = $verifiedDomainDao ?: new VerifiedDomainDao();

        $this->emailpreprocessor = $emailPreprocessor ?: LoadLibrary::emailPreprocessor();
        $this->providerFactory = $providerFactory ?: new ProviderFactory();

        $this->emailDomainBlacklist = $emailDomainBlacklist ?: LoadLibrary::emailDomainBlacklist();
        $this->combinedBilling = $combinedBilling; // Lazy load when needed due to circular dependency
        $this->quotaEnforcer = $quotaEnforcer ?: new ExternalSmtpQuotaEnforcer();
        $this->smtpConfigService = $smtpConfigService ?: new UserSmtpConfigurationService();
    }

    /**
     * Can hopefully be the core of a generic "sendEmail" method that consolidates all of the various methods that call it
     *
     * @param int $companyID
     * @param Email $unprocessedEmail
     * @param RecipientGenerator $recipient
     * @param SendOptions $options
     * @param string[] $extraAttachmentURLs
     *
     * @return StandardEmail
     *
     * @throws MailshipException
     */
    public function prepSend(
        int $companyID,
        Email $unprocessedEmail,
        RecipientGenerator $recipient,
        SendOptions $options,
        array $extraAttachmentURLs = []
    ): StandardEmail {
        if (!empty($unprocessedEmail->id) && empty($unprocessedEmail->isActive)) {
            SSLog::logLazyTrace('inactive email', 0, SSLog::TYPE_LOG, 'emaildelivery', ['companyID' => $companyID]);
            throw new MailshipException(
                'Email is inactive',
                MailshipException::INACTIVE_EMAIL
            );
        }

        if (!$recipient->isValid()) {
            SSLog::logLazyTrace('invalid recipient', 0, SSLog::TYPE_LOG, 'emaildelivery', ['companyID' => $companyID]);
            throw new MailshipException(
                'Recipient is invalid (non-existant list or lead, or invalid address)',
                MailshipException::INVALID_RECIPIENT
            );
        }

        if ($options->requireSpamCompliant) {
            if (!$this->companyprofile_model->isSpamCompliantByID($companyID)) {
                throw new MailshipException(
                    'Company is missing spam compliance info',
                    MailshipException::NOT_SPAM_COMPLIANT
                );
            }
        }

        // Generate the html for an e-mail & process merge variables
        $standardEmail = $this->emailpreprocessor->generateStandardEmail(
            $companyID,
            $unprocessedEmail,
            $options,
            $extraAttachmentURLs,
            $recipient->leadID ?? null
        );

        if (!$standardEmail->containsRequiredFields($options->useMergeVariables)) {
            SSLog::log('Could not generateStandardEmail', SSLog::TYPE_DEBUG, 'emaildelivery', [
                'companyID' => $companyID,
                'unprocessedEmail' => $unprocessedEmail,
                'recipient' => $recipient->toArray(),
            ]);
            throw new MailshipException(
                'Failed to generateStandardEmail',
                MailshipException::STANDARD_EMAIL_FAILED
            );
        }

        $standardEmail->validateAddresses();

        // Check that we're authorized to send from the domain we're using
        //FIXME: this seems like it ought to be combined with the required DKIM stuff
        $sendResult = $this->canSendFromDomain($companyID, $standardEmail, $options);
        if (!$sendResult->getCanSend()) {
            $sendResult->notifyBlockedSends(
                $standardEmail,
                $recipient->getName(),
                $recipient->getMemberCount(),
                $recipient->isListSend(),
                $options
            );
            throw new MailshipException(
                'Company cannot send mail from this domain (blacklisted or not verified)',
                MailshipException::CANNOT_SEND_FROM_DOMAIN
            );
        }

        return $standardEmail;
    }

    /**
     * @param int $companyID
     * @param StandardEmail $email
     * @param RecipientGenerator $recipient
     * @param SendOptions $options
     * @param bool|null $isMaFreeTrial
     *
     * @throws MailshipException
     */
    public function deductSends($companyID, StandardEmail $email, RecipientGenerator $recipient, SendOptions $options, $isMaFreeTrial = null)
    {
        if ($options->isSystemEmail) {
            return;
        }

        $numMessages = $recipient->getMemberCount();

        $result = null;
        if ($numMessages === false) {
            $result = new CanSendResult(
                $companyID,
                false,
                false,
                null,
                CanSendResult::DEFAULT_ERROR
            );
        } else {
            $result = $this->canSend(
                $companyID,
                $numMessages,
                null,
                true,
                EmailAddressCompany::getDomain($email->fromEmail),
                $options->authorID,
                $options->useCustomSmtp
            );
        }

        if ($isMaFreeTrial || $result->getCanSend()) {
            //Deduct the credits
            $this->companyEmailAccountDao->deductSends($companyID, $numMessages);

            $result->deductSends($numMessages);
            $result->setDetails(['memberCount' => $numMessages]);
        } else {
            $result->notifyBlockedSends($email, $recipient->getName(), $numMessages, $recipient->isListSend(), $options);
            throw new MailshipException('Send would put company over quota', MailshipException::OVER_QUOTA);
        }
    }

    /**
     * @param int $companyID
     * @param int $numMessages
     * @param DateTime|string|null $when
     * @param bool $checkDKIM
     * @param string $domain
     * @param int|null $authorID
     * @param bool $useCustomSmtp
     *
     * @return CanSendResult
     */
    public function canSend(
        int $companyID,
        int $numMessages,
        $when = null,
        bool $checkDKIM = true,
        string $domain = '',
        int $authorID = null,
        $useCustomSmtp = false
    ) {
        //TODO: Integrate this with the canSpam compliance

        $companyProfile = $this->companyprofile_model->get($companyID);
        $offering = new Offering($companyProfile['productOffering']);
        if ($offering->hasOffering(Offering::CRM_OFFERING_MASK) || $useCustomSmtp) {
            $checkDKIM = false;
        }

        if ($checkDKIM && !$this->isDKIMAuth($companyID, $domain)) {
            $result = new CanSendResult($companyID, false, false, null, CanSendResult::DKIM_NOT_SETUP);
            $result->setDetails(['domain' => $domain]);
            return $result;
        }

        // We create this exception to allow a company to send regardless of status
        if ($companyID == MY_Model::SS_COMPANY_ID || $companyID == self::PA_COMPANY_ID) {
            return new CanSendResult($companyID, true, true);
        }

        if ($this->companyprofile_model->isSendingDisabled($companyID)) {
            return new CanSendResult($companyID, false, false, null, CanSendResult::SENDING_DISABLED);
        }

        if ($useCustomSmtp) {
            // TODO: different handling for future scheduled sends?
            $smtpSettings = $this->smtpConfigService->getForUser($companyID, $authorID);
            if (empty($smtpSettings->username) || empty($smtpSettings->host)) {
                return new CanSendResult($companyID, false, false, null, CanSendResult::SMTP_NOT_CONFIGURED);
            }
            $allowed = $this->quotaEnforcer->getRemaining($smtpSettings->username, $smtpSettings->host, $companyID);
            if ($numMessages > $allowed) {
                return new CanSendResult($companyID, false, false, $allowed, CanSendResult::SMTP_RATE_LIMIT);
            }
        }

        //Check if overages are allowed based on the company's plan
        $allowOverage = null;
        $planType = $this->companyprofile_model->getPlanType($companyID);
        switch ($planType) {
            case CompanyProfile_model::PLAN_PROMOTIONAL:
            case CompanyProfile_model::PLAN_ESP:
            case CompanyProfile_model::PLAN_ESP_RESELLER:
            case CompanyProfile_model::PLAN_ESP_RESELLER_CLIENT:
            case CompanyProfile_model::PLAN_MARKETING_AUTOMATION:
            case CompanyProfile_model::PLAN_MARKETING_AUTOMATION_RESELLER:
            case CompanyProfile_model::PLAN_MARKETING_AUTOMATION_RESELLER_CLIENT:
                $allowOverage = true;
                break;
            case CompanyProfile_model::PLAN_ESP_RESELLER_CAPPED_CLIENT:
            case CompanyProfile_model::PLAN_ESP_RESELLER_CONTACT_CLIENT:
            case CompanyProfile_model::PLAN_CONTACT:
            case CompanyProfile_model::PLAN_MARKETING_AUTOMATION_RESELLER_CAPPED_CLIENT:
            case CompanyProfile_model::PLAN_CRM_TIER_ZERO_INTRO:
            case CompanyProfile_model::PLAN_CRM_TIER_ZERO:
            case CompanyProfile_model::PLAN_CRM_TIER_ONE:
            case CompanyProfile_model::PLAN_CRM_TIER_TWO:
                $allowOverage = false;
                break;
            case CompanyProfile_model::PLAN_INACTIVE:
                return new CanSendResult($companyID, false, false, null, CanSendResult::INACTIVE);
            default:
                return new CanSendResult($companyID, false, false, null, CanSendResult::DEFAULT_ERROR);
        }

        //Make sure this company exists and uses a sends plan (and has enough credits if we don't allow overages)
        $emailAccount = $this->companyEmailAccountDao->getByCompanyID($companyID);

        if (!$emailAccount) {
            return new CanSendResult($companyID, $allowOverage, $allowOverage, null, CanSendResult::DEFAULT_ERROR);
        }

        if ($emailAccount->sendStatus == CompanyEmailAccount::SEND_STATUS_PENDING_APPROVAL) {
            return new CanSendResult($companyID, false, false, null, CanSendResult::PENDING_APPROVAL);
        }

        if ($when !== null) {
            if (empty($this->combinedBilling)) {
                $this->combinedBilling = LoadLibrary::combinedBilling();
            }
            $inCurrentSendPeriod = $this->combinedBilling->isInCurrentSendLimitPeriod((int)$companyID, $when);

            if (!$inCurrentSendPeriod) {
                //Don't try to keep track of send allowances for future scheduled sends
                return new CanSendResult($companyID, true, $allowOverage);
            }
        }

        if ($emailAccount->remainingSends === null) {
            //No limit on their sends
            return new CanSendResult($companyID, true, $allowOverage);
        }

        if (!$allowOverage && $emailAccount->remainingSends < $numMessages) {
            return new CanSendResult($companyID, false, $allowOverage, $emailAccount->remainingSends, CanSendResult::OVER_QUOTA);
        }

        return new CanSendResult($companyID, true, $allowOverage, $emailAccount->remainingSends);
    }

    /**
     * Checks if the domain is DKIM authenticated (preview emails should not do this check)
     *
     * @param int $companyID
     * @param string $domain
     *
     * @return bool
     */
    public function isDKIMAuth(int $companyID, string $domain): bool
    {
        //TODO: phase this flag out once we have better ways to track our one-off exceptions
        if (getFlag('DKIMNotSetup', $companyID)) {
            return true;
        }

        $company = $this->companyprofile_model->getByID($companyID);
        if ($company) {
            // Newly created companies have a 14 day grace period
            if (strtotime($company['createTimestamp']) > strtotime('-14 day')) {
                return true;
            }

            // Mail+ instances don't need DKIM
            $offering = new Offering($company['productOffering']);
            if ($offering->hasOffering($offering::ESP)) {
                return true;
            }

            if (!empty($userID)) {
                $smtpSettings = $this->smtpConfigService->getForUser($companyID, $userID);
                // if smtp is setup then don't need dkim
                if (!empty($smtpSettings)) {
                    return true;
                }
            }

            // Also automatically pass all test companies
            if ($company['flags'] & CompanyProfile_model::FLAG_TEST_ACCOUNT) {
                return true;
            }
        } else {
            SSLog::log(
                'Unable to find company for DKIM check',
                SSLog::TYPE_DEBUG,
                'email',
                ['companyID' => $companyID, 'domain' => $domain]
            );
        }

        $companyEmailAccount = $this->companyEmailAccountDao->getByCompanyID($companyID);
        $sendingDomainProvider = $this->providerFactory->createSendingDomainProvider($companyEmailAccount);
        return $sendingDomainProvider->hasVerifiedDKIM($domain);
    }

    /**
     * @param int $companyID
     *
     * @return bool Whether this send needs to be through Custom SMTP.
     */
    public function useCustomSmtp(int $companyID): bool
    {
        $company = $this->companyprofile_model->get($companyID);
        $offering = new Offering($company);
        $isCrm = $offering->isCrm();
        $forceCustomSmtp = $this->isCustomSmtpForced($companyID);

        // FreeCRM always requires Custom SMTP.
        return $isCrm || $forceCustomSmtp;
    }

    /**
     * Check for forced Custom SMTP company service flag.
     * TODO: add caching to this since it's used in automation
     *
     * @param int $companyID
     *
     * @return bool
     */
    private function isCustomSmtpForced(int $companyID): bool
    {
        $companySvc = new CompanyService();
        $throwOnError = ENVIRONMENT !== 'development';
        return $companySvc->isServiceEnabled($companyID, 'customSmtpSendOnly', $throwOnError);
    }

    /**
     * Similar to useCustomSmtp but with consideration for if the user has Custom SMTP set up.
     * @param int $companyID
     * @param int $userID
     *
     * @return bool Whether this smart mail send needs to be through Custom SMTP.
     */
    public function useCustomSmtpForSmartMail(int $companyID, int $userID): bool
    {
        return $this->useCustomSmtp($companyID)
            || (bool)$this->smtpConfigService->getForUser($companyID, $userID);
    }

    /**
     * FIXME: take SendOptions, not the individual flags
     *
     * @param int $companyID
     * @param StandardEmail $standardEmail
     * @param SendOptions $sendOptions
     *
     * @return CanSendResult
     */
    private function canSendFromDomain(
        int $companyID,
        StandardEmail $standardEmail,
        SendOptions $sendOptions
    ) {
        $company = $this->companyprofile_model->get($companyID);
        $offering = new Offering($company['productOffering'] ?? 1);
        $isCustomSmtp = $offering->hasOffering(
            Offering::CRM_TIER_ZERO
            | Offering::CRM_TIER_ZERO_INTRO
            | Offering::CRM_TIER_ONE
            | Offering::CRM_TIER_TWO
        ) || $sendOptions->useCustomSmtp;

        if (!$sendOptions->isSystemEmail && !$isCustomSmtp) {
            // System email skips the blacklist check, as many are sent from donotreply@sharpspring.com or similar
            // Custom smtp also skips this check
            $blacklistCheck = $this->emailDomainBlacklist->isValidSendAddress($companyID, $standardEmail->fromEmail);
            if (!empty($blacklistCheck) && $blacklistCheck['valid'] === false) {
                SSLog::log('Email blacklist triggered', SSLog::TYPE_DEBUG, 'email', [
                    'companyID' => $companyID,
                    'standardEmail' => (array)$standardEmail,
                    'blacklistDetails' => $blacklistCheck,
                ]);

                $result = new CanSendResult($companyID, false, false, null, CanSendResult::BLACKLISTED_DOMAIN);
                $result->setDetails([
                    'domain' => $blacklistCheck['domain'],
                    'match' => $blacklistCheck['match'],
                    'fromEmail' => $standardEmail->fromEmail,
                ]);
                return $result;
            }
        }

        $result = new CanSendResult($companyID, true, false);

        // Don't check verified domain for customSmtp
        if ($isCustomSmtp) {
            return $result;
        }

        // this is not on preview email, but may be generated other email sends and should prevent earlier created emails from being verified.
        if ($standardEmail->updateTimestamp && strtotime($standardEmail->updateTimestamp) < strtotime('2016-06-15')) {
            return $result;
        }

        // As it is possible to change the fromEmail for just one send,
        // grab by domain we are actually using for this send
        // rather than stored verifiedDomainID
        //FIXME: this should be baked into the DKIM check already, not separately evaluated here
        $domain = $this->verifiedDomainDao->getByDomain($companyID, EmailAddressCompany::getDomain($standardEmail->fromEmail));
        if ($domain && $domain->verificationStatus == VerifiedDomain::STATUS_VERIFIED && $domain->isActive) {
            return $result;
        }

        // This is flagged when an email is being sent for verification and passes the blacklisted domains
        if ($sendOptions->isSystemEmail && !$sendOptions->isPreviewEmail) {
            return $result;
        }

        $result = new CanSendResult($companyID, false, false, null, CanSendResult::UNVERIFIED_DOMAIN);
        $result->setDetails([
            'fromEmail' => $standardEmail->fromEmail,
            'vDomain' => $domain,
        ]);
        return $result;
    }
}
