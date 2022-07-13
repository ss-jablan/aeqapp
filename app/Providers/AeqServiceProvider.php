<?php

namespace App\Providers;

use App\Automation\AutomationEventMap;
use App\Automation\Constants\AutomationEventType;
use App\Exceptions\AutomationException;
use App\Exceptions\RetryableAutomationException;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Sharp\Models\Automation\ListModel;

class AeqServiceProvider extends ServiceProvider
{
    /**
     * @var int
     */
    const ALLOW_RETRY = 1;
    /**
     * @var int
     */
    const TOO_MANY_RETRIES = 2;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * @param array $event
     *
     * @return void
     */
    public function showDevHelpDispatchEvent(array $event)
    {
        if (!App::environment('local')) {
            return;
        }

        echo "SUCCESS: AutomationEventRunner dispatched <{$event['eventType']}> event\n";
        echo "  id:                {$event['id']}, companyProfileID: {$event['companyProfileID']}\n";
        echo "  workflowID:        {$event['workflowID']}, taskID: {$event['taskID']}, originatingLeadID: {$event['originatingLeadID']}\n";
        echo "  triggerData:       " . print_r($event['triggerData'], true);
        echo "  workflowEventData: " . print_r($event['workflowEventData'], true);
        echo "-----------------------------------------------------------------\n";
    }

    /**
     * Return a concise version of the event
     *
     * @param array|null $event
     *
     * @return array|null
     */
    public function trimEvent(?array $event): ?array
    {
        if (isset($event['workflowEventData']['email'])) {
            $event['workflowEventData']['email']['emailHTML'] = '';
            $event['workflowEventData']['email']['thumbnail'] = '';
            $event['workflowEventData']['email']['thumbnailPath'] = '';
        }

        return $event;
    }

    /**
     * Dispatches an action the correct function based on the eventType. Each of these actions
     * is a method that processes the event (and any embedded data) and attempts to complete
     * the request. An event matches up to information in an automationEventQueue row.
     *
     * Event example:
     * Array(
     *     [id] => 1
     *     [companyProfileID] => 3
     *     [eventType] => sendEmail
     *     [eventScheduled] => 2013-05-09 13:00:00
     *     [processed] => 0
     *     [data] => []
     *     [createTimestamp] => 2013-05-09 14:09:58
     * )
     *
     * @param array|null $event (see above)
     *
     * @throws \Exception
     */
    public function dispatch(?array $event)
    {
        if (empty($event)) {
            return;
        }

        $eventType = $event['eventType'];

        if (empty($eventType) || !AutomationEventMap::validEvent($eventType)) {
            echo "dispatchEvent - found invalid event type: $eventType\n";
            Log::error('Invalid event type', ['event' => $event]);

            return;
        }

        $this->showDevHelpDispatchEvent($event);

        switch ($eventType) {
            case AutomationEventType::SEND_EMAIL:
            case AutomationEventType::SEND_EMAIL_TO_REFERRER:
                $this->runSendEmail($event);
                break;
            case AutomationEventType::SEND_NOTIFICATION: // aka `sendNotificationToUser`
            case AutomationEventType::SEND_NOTIFICATION_EMAIL:
            case AutomationEventType::SEND_NOTIFICATION_EMAIL_TO_REFERRER:
                $this->runSendNotificationToUserOrEmail($event);
                break;
            case AutomationEventType::SEND_EMAIL_TO_LIST:
                $this->runSendEmailToList($event);
                break;
            case AutomationEventType::SEND_ONE_OFF_EMAIL:
                $this->runSendOneOffEmail($event);
                break;
            case AutomationEventType::ASSIGN_LEAD_CAMPAIGN:
                // passes $triggeredByWorkflowID
                $this->runAssignLeadCampaign($event);
                break;
            case AutomationEventType::ASSIGN_LEAD_OWNER:
                // passes $triggeredByWorkflowID
                $this->runAssignLeadOwner($event);
                break;
            case AutomationEventMap::REMOVE_FROM_WORKFLOW:
            case AutomationEventType::REMOVE_FROM_ACTION_GROUP:
                $this->runRemoveFromWorkflow($event);
                break;
            case AutomationEventType::REMOVE_FROM_VISUAL_WORKFLOW:
            case AutomationEventType::REMOVE_FROM_OPPORTUNITY_WORKFLOW:
                $this->runRemoveFromVisualWorkflow($event);
                break;
            case AutomationEventType::CHANGE_LEAD_FIELD:
                // passes $triggeredByWorkflowID
                $this->runChangeLeadField($event);
                break;
            case AutomationEventType::CHANGE_OPPORTUNITY_FIELD:
                $this->runChangeOpportunityField($event);
                break;
            case AutomationEventType::CHANGE_LEAD_PERSONA:
                // passes $triggeredByWorkflowID
                $this->runChangeLeadPersona($event);
                break;
            case AutomationEventType::INCREMENT_COUNTER_FIELD:
                // passes $triggeredByWorkflowID
                $this->runIncrementCounterField($event);
                break;
            case AutomationEventType::DECREMENT_COUNTER_FIELD:
                // passes $triggeredByWorkflowID
                $this->runDecrementCounterField($event);
                break;
            case AutomationEventType::ADD_TO_LIST:
                $this->runAddToList($event);
                break;
            case AutomationEventType::ADD_TO_LISTS_WITH_TAG:
                $this->runAddToListsWithTag($event);
                break;
            case AutomationEventType::REMOVE_FROM_LIST:
                $this->runRemoveFromList($event);
                break;
            case AutomationEventType::REMOVE_FROM_LISTS_WITH_TAG:
                $this->runRemoveFromListsWithTag($event);
                break;
            case AutomationEventType::CHANGE_LEAD_STATUS:
                // passes $triggeredByWorkflowID
                $this->runChangeLeadStatus($event);
                break;
            case AutomationEventType::POSTBACK_LEAD:
                $this->runPostBackLead($event);
                break;
            case AutomationEventType::POSTBACK_OPPORTUNITY:
                $this->runPostBackOpportunity($event);
                break;
            case AutomationEventType::SOCIAL_INVITE:
                // This doesn't appear to have done anything for quite some time
                // Should this case even exist anymore?
                break;
            case AutomationEventType::RSS_EMAIL:
                $this->runRSSEmail($event);
                break;
            case AutomationEventType::ADD_TO_WORKFLOW :
            case AutomationEventType::ADD_TO_ACTION_GROUP:
                $this->runAddToActionGroup($event);
                break;
            case AutomationEventType::ADD_TO_VISUAL_WORKFLOW:
                $this->runAddToVisualWorkflow($event);
                break;
            case AutomationEventType::CREATE_OPPORTUNITY:
                $this->runCreateOpportunity($event);
                break;
            case AutomationEventType::CREATE_TASK:
                $this->runCreateTask($event);
                break;
            case AutomationEventType::CHANGE_OPPORTUNITY_STAGE:
                $this->runChangeOpportunityStage($event);
                break;
            case AutomationEventType::CHANGE_OPPORTUNITY_STATUS:
                $this->runChangeOpportunityStatus($event);
                break;
            case AutomationEventType::ASSIGN_OPPORTUNITY_OWNER:
                $this->runAssignOpportunityOwner($event);
                break;
            case AutomationEventType::CONVERSION_GOAL_MET:
                $this->runConversionGoalMet($event);
                break;
            case AutomationEventType::TEST_EVENT:
                /** @noinspection PhpExpressionResultUnusedInspection */
                $this->runTest($event);
                break;
            case AutomationEventType::ADD_LEAD_TAG:
                $this->runAddTagToLead($event);
                break;
            case AutomationEventType::REMOVE_LEAD_TAG:
                $this->runRemoveTagFromLead($event);
                break;
            case AutomationEventType::YESNO_BRANCH:
                $this->runYesNoBranch($event);
                break;
            // should be unreachable
            default:
                throw new Exception('Failed to match to an event type.');
        }
    }

//    /**
//     * @param array $event
//     *
//     * @return int
//     */
//    public function shouldRetryEvent(array $event)
//    {
//        $eventID = $event['id'];
//        $cacheKey = "eventrunner:retries:$eventID";
//        $numRetries = $this->cacheModel->get($cacheKey);
//        if ($numRetries >= 10) {
//            return static::TOO_MANY_RETRIES;
//        }
//        $this->cacheModel->incr($cacheKey, 1);
//
//        return static::ALLOW_RETRY;
//    }
//
//    /**
//     * Log email send error
//     *
//     * @param int       $companyID
//     * @param int       $emailJobID
//     * @param int       $error
//     * @param Exception $e
//     * @param int       $severity
//     * @param int|null  $leadID
//     *
//     * @throws Exception
//     */
//    private function logEmailSendErrorAutomationEvent(
//        $companyID,
//        $emailJobID,
//        $error,
//        Exception $e,
//        $severity = LogEmailError::NOTIFY,
//        $leadID = null
//    ) {
//        $error = [
//            'result' => $error,
//            'ts'     => date('Y-m-d H:i:s'),
//            'msg'    => $e->getMessage(),
//            'code'   => $e->getCode(),
//        ];
//
//        $logEmailError = new LogEmailError($companyID, $emailJobID, $error, $severity, $leadID);
//        App::get()->mq->publish($logEmailError);
//    }
//
//    /**
//     * Find the lead owner email address
//     *
//     * @param int $companyID
//     * @param int $leadID
//     *
//     * @return string|bool The email address or FALSE if not found or no lead owner
//     */
//    protected function getLeadOwnerEmailAddress(int $companyID, int $leadID)
//    {
//        $lead = LoadModel::lead()->get($companyID, $leadID, ['ownerID']);
//        if (!empty($lead['ownerID'])) {
//            $leadOwner = LoadModel::userProfile()->getWhereSingle(
//                $companyID,
//                [
//                    'id'       => $lead['ownerID'],
//                    'isActive' => true,
//                ]
//            );
//            if (!empty($leadOwner['emailAddress'])) {
//                return $leadOwner['emailAddress'];
//            }
//        }
//
//        return false;
//    }

//    /**
//     * Send an email to a single lead
//     *
//     * @param array $event
//     *
//     * @throws AutomationException
//     * @throws RetryableAutomationException
//     * @throws Exception
//     */
//    public function runSendEmail($event)
//    {
//        $isValid = $this->isSendEmailEventValid($event);
//        if (!$isValid) {
//            return;
//        }
//
//        $leadID = $event['triggerData']['whoID'];
//        $companyID = $event['workflowEventData']['email']['companyProfileID'];
//        $metadata = [
//            'co'         => $companyID,
//            'l'          => $leadID,
//            'identifier' => 'eventRunner::runSendEmail',
//            'details'    => json_encode($event),
//        ];
//
//        if (isset($event['workflowEventData']['toReferrer']) && $event['workflowEventData']['toReferrer']) {
//            $leadID = $this->leadReferralModel->getReferrerID($companyID, $leadID);
//            if (!$leadID) {
//                Log::debug(
//                    'automation event did not send email (referring lead not found)',
//                    'AutomationEventRunner->runSendEmail',
//                    $metadata
//                );
//
//                return;
//            }
//        }
//        $emailID = $event['workflowEventData']['email']['id'] ?? null;
//        $workflowID = $event['workflowEventData']['workflowID'] ?? null;
//
//        // get the lead who the email is being sent to
//        $email = $this->emailModel->getByID($companyID, $emailID);
//
//        if (empty($email) || $email['isActive'] != 1) {
//            Log::debug(
//                'automation event did not send email (email not found)',
//                'AutomationEventRunner->runSendEmail',
//                $metadata
//            );
//            if (empty($email['isActive'])) {
//                throw new AutomationException('email is not active');
//            } else {
//                throw new RetryableAutomationException('email not found');
//            }
//        }
//
//        $lead = $this->leadModel->getByLeadID($leadID, $companyID);
//        if (!$lead) {
//            Log::debug(
//                'automation event did not send email (lead not found)',
//                'AutomationEventRunner->runSendEmail',
//                $metadata
//            );
//
//            return;
//        }
//
//        $workflow = $this->workflowModel->get($companyID, $workflowID);
//        $sendDuplicate = $email['allowDuplicateSend'] || !empty($workflow['testMode']);
//
//        $unprocessedEmail = LoadModel::emailDao()->get($companyID, $emailID);
//
//        //TODO: read some extra workflowEventData flag for bulk action groups scheduled to lists here
//        $companyProfile = LoadModel::companyProfile()->get($companyID);
//        if ($companyProfile['managedBy'] === $companyProfile['id'] && $companyProfile['isReseller']) {
//            $companyType = SendOptions::COMPANY_TYPE_AGENCY;
//        } elseif ($companyProfile['managedBy'] === $companyProfile['id'] && !$companyProfile['isReseller']) {
//            $companyType = SendOptions::COMPANY_TYPE_DIRECT_CLIENT;
//        } else {
//            $companyType = SendOptions::COMPANY_TYPE_AGENCY_CLIENT;
//        }
//
//        $offering = new Offering($companyProfile['productOffering']);
//        $isFreeOffering = $offering->hasOffering(Offering::FREE_OFFERING_MASK);
//        $primaryOffering = $offering->getPrimaryOffering();
//
//        $optionsParams = [
//            'workflowID'        => $workflowID,
//            'sendDuplicate'     => $sendDuplicate,
//            'authorID'          => $event['triggerData']['userID'] ?? null,
//            'useCustomSmtp'     => $event['triggerData']['useCustomSmtp'] ?? null,
//            'isFreeOffering'    => $isFreeOffering,
//            'companyType'       => $companyType,
//            'offering'          => $primaryOffering,
//            'suppressAtRisk'    => $event['workflowEventData']['suppressAtRisk'] ?? false,
//            'suppressUnengaged' => $event['workflowEventData']['suppressUnengaged'] ?? true,
//        ];
//
//        $options = new SendOptions('lead', $optionsParams);
//        $recipient = new SingleLeadRecipient($companyID, $leadID);
//
//        $cacheModel = LoadModel::cache();
//        $sendDupeCacheKey = "automationSendDupe::$companyID:$leadID:$emailID";
//        try {
//            if (!$sendDuplicate) {
//                $lock = $cacheModel->add($sendDupeCacheKey, 1);
//                if (!$lock) {
//                    $memcachedResultCode = $cacheModel->memcached->getResultCode();
//                    if ($memcachedResultCode === Memcached::RES_NOTSTORED
//                        || $memcachedResultCode === Memcached::RES_DATA_EXISTS
//                    ) {
//                        throw new AutomationException('duplicate single lead email send');
//                    }
//
//                    App::get()->logger->error(
//                        'failed to add lock',
//                        [
//                            'cacheKey'   => $sendDupeCacheKey,
//                            'lock'       => $lock,
//                            'resultCode' => $memcachedResultCode,
//                        ]
//                    );
//                }
//            }
//
//            $this->emailDelivery->sendEmailToLead($companyID, $unprocessedEmail, $recipient, $options);
//        } catch (RetryableAutomationException|RetryableMailshipException $e) {
//            $this->logEmailSendErrorAutomationEvent(
//                $companyID,
//                $options->emailJobID,
//                Mailer::RESULT_FAILED_MAILSHIP,
//                $e,
//                LogEmailError::NOTIFY,
//                $leadID
//            );
//            throw new RetryableAutomationException('Error sending email to lead', 0, $e);
//        } catch (AutomationException $e) {
//            // catch just so that we re-throw so that it doesn't get caught below
//            throw $e;
//        } catch (MailshipException $e) {
//            $severity = $e->getCode() == MailshipException::NOT_ELIGIBLE
//                ? LogEmailError::SILENT : LogEmailError::NOTIFY;
//            $this->logEmailSendErrorAutomationEvent(
//                $companyID,
//                $options->emailJobID,
//                Mailer::RESULT_FAILED_MAILSHIP,
//                $e,
//                $severity,
//                $leadID
//            );
//            throw new AutomationException($e->getMessage(), 0, $e);
//        } catch (RuntimeException $e) {
//            $this->logEmailSendErrorAutomationEvent(
//                $companyID,
//                $options->emailJobID,
//                Mailer::RESULT_FAILED,
//                $e,
//                LogEmailError::NOTIFY,
//                $leadID
//            );
//            throw new RetryableAutomationException('hit runtime exception', 0, $e);
//        }
//        finally {
//            if (!$sendDuplicate) {
//                $cacheModel->kill($sendDupeCacheKey);
//            }
//        }
//    }
//
//    /**
//     * Checks whether an event contains all of the proper fields for sending email.
//     * This function treats all companies with custom SMTP delivery as having invalid events.
//     *
//     * @param array $event
//     *
//     * @return bool true if it is a valid event or false if not
//     */
//    private function isSendEmailEventValid(array $event): bool
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['email'])
//            || !isset($event['workflowEventData']['email']['companyProfileID'])
//            || !isset($event['workflowEventData']['email']['id'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['whoType'])
//            || !isset($event['triggerData']['whoID'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || $event['triggerData']['whoType'] != 'lead'
//        ) {
//            Log::error(
//                'automation event failed to send email',
//                'AutomationEventRunner->runSendEmail',
//                [
//                    'identifier' => 'eventRunner::runSendEmail',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return false;
//        }
//        $companyID = $event['workflowEventData']['email']['companyProfileID'];
//
//        // Don't process these automation events if they are forced to use Custom SMTP.
//        // TODO: Support the use of Custom SMTP. (SRSP-38848 :alleg:)
//        $forceCustomSmtp = LoadLibrary::emailDelivery()->useCustomSmtp($companyID);
//        if ($forceCustomSmtp) {
//            return false;
//        }
//
//        return true;
//    }
//
//    /**
//     * Queues sending an email to a list (via an MQ payload)
//     *
//     * @param $event
//     *
//     * @throws Exception
//     */
//    public function runSendEmailToList($event)
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['sendDuplicate'])
//            || !isset($event['workflowEventData']['email']['companyID'])
//            || !isset($event['workflowEventData']['email']['fromEmail'])
//            || !isset($event['workflowEventData']['email']['fromName'])
//            || !isset($event['workflowEventData']['email']['id'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['whoID'])
//        ) {
//            Log::error(
//                'automation event failed to send email to list',
//                [
//                    'identifier' => 'eventRunner::runSendEmailToList',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $companyID = $event['workflowEventData']['email']['companyID'];
//        $emailID = $event['workflowEventData']['email']['id'];
//        $suppressAtRisk = $event['workflowEventData']['suppressAtRisk'] ?? false;
//        $suppressUnengaged = $event['workflowEventData']['suppressUnengaged'] ?? true;
//        $workflowID = !empty($event['workflowEventData']['workflowID']) ? $event['workflowEventData']['workflowID'] : null;
//        $authorID = $event['workflowEventData']['authorID'] ?? $event['triggerData']['authorID'] ?? null;
//
//        $email = $this->emailModel->getByID($companyID, $emailID);
//        if (empty($email['isActive'])) {
//            // TODO: should this be checked later in the sending process (generateStandardEmail maybe)? It'd get rid of the emailModel dependency here
//            throw new AutomationException();
//        }
//
//        if ($workflowID) {
//            $workflow = $this->workflowModel->get($companyID, $workflowID);
//        } else {
//            $workflow = null;
//        }
//
//        $sendDuplicate = $event['workflowEventData']['sendDuplicate']
//            || $email['allowDuplicateSend']
//            || !empty($workflow['testMode']);
//
//        $recipientID = $event['triggerData']['whoID'];
//        if (is_array($recipientID)) {
//            $recipientID = reset($recipientID);
//        }
//        // FIXME: Send this value from automation
//        $recipientType = $event['triggerData']['recipientType'] ?? EmailJob::RECIPIENT_TYPE_LIST;
//
//        $uniqueID = "$recipientID:$emailID:$workflowID";
//        if ($recipientType == EmailJob::RECIPIENT_TYPE_MULTI) {
//            $uniqueID .= ':multi';
//        }
//
//        App::get()->mq->publish(new SendEmailToList(
//            $companyID,
//            $recipientType,
//            $recipientID,
//            $emailID,
//            $workflowID,
//            $sendDuplicate,
//            $suppressAtRisk,
//            $suppressUnengaged,
//            $authorID,
//            $event['eventScheduled'],
//            $event['triggerData']['userID'] ?? null,
//            $event['triggerData']['useCustomSmtp'] ?? null
//        ),
//            (new TaskOptions())->setUnique($uniqueID));
//    }
//
//    /**
//     * Queues sending an RSS email to a list (via an MQ payload)
//     *
//     * @param $event
//     *
//     * @throws Exception
//     */
//    public function runRSSEmail($event)
//    {
//        // whoID should be the listID, but we're just grabbing it from the emailFeedSubscription table
//        if (empty($event['whatID']) || empty($event['companyProfileID'])) {
//            Log::log(
//                'automation event failed to send RSS email',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runRSSEmail',
//                [
//                    'identifier' => 'eventRunner::runRSSEmail',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $companyID = $event['companyProfileID'];
//        $feedID = $event['whatID'];
//
//        $emailFeedSubscriptionModel = LoadModel::emailFeedSubscription();
//
//        $feedSub = $emailFeedSubscriptionModel->get($companyID, $feedID);
//        if (empty($feedSub['isActive'])) {
//            throw new AutomationException();
//        }
//
//        $emailID = $feedSub['emailID'];
//        $email = $this->emailModel->getByID($companyID, $emailID);
//        if (empty($email['isActive'])) {
//            throw new AutomationException();
//        }
//
//        $listID = $feedSub['listID'];
//        $list = $this->listModel->get($companyID, $listID);
//        if (!$list) {
//            throw new AutomationException();
//        }
//
//        $feed = $emailFeedSubscriptionModel->getFeed($feedSub);
//
//        // Do not send if there are no new items
//        $newestFeedItem = $emailFeedSubscriptionModel->getFeedItem($feed, 0);
//        if (empty($newestFeedItem) || $newestFeedItem['itemID'] == $feedSub['lastItem']) {
//            Log::log(
//                'rssEmail scheduled, but no new items',
//                Log::TYPE_DEBUG,
//                'rssEmail',
//                [
//                    'co'         => $companyID,
//                    'lastItem'   => $feedSub['lastItem'],
//                    'newestItem' => $newestFeedItem['itemID'] ?? null,
//                    'sub'        => $feedSub,
//                ]
//            );
//
//            return;
//        }
//
//        App::get()->mq->publish(new SendEmailToList(
//            $companyID,
//            EmailJob::RECIPIENT_TYPE_LIST,
//            $listID,
//            $emailID,
//            null,
//            true, // RSS emails always allow duplicates
//            $feedSub['suppressAtRisk'] ?? false,
//            $feedSub['suppressUnengaged'] ?? true,
//            null,
//            $event['eventScheduled'],
//            null,
//            null
//        ),
//            (new TaskOptions())->setUnique("$listID:$emailID:"));
//
//        //store most recent item sent
//        $emailFeedSubscriptionModel->updateSubscription($companyID, $feedID, ['lastItem' => $newestFeedItem['itemID']]);
//    }
//
//    /**
//     * Send an email to a single lead, with customizations put in place for this single send
//     *
//     * @param $event
//     *
//     * @throws AutomationException
//     * @throws RetryableAutomationException
//     * @throws Exception
//     */
//    public function runSendOneOffEmail($event)
//    {
//        // Making this consistent with the other send email calls
//        // but must maintain back-compat to when email data was not separate
//        if (isset($event['workflowEventData']['email'])) {
//            $email = $event['workflowEventData']['email'];
//        } else {
//            $email = $event['workflowEventData'];
//        }
//
//        if (!isset($event['workflowEventData'])
//            || !isset($email['id'])
//            || !isset($email['subject'])
//            || !isset($email['emailHTML'])
//            || !isset($email['fromName'])
//            || !isset($email['fromEmail'])
//            || !isset($event['whoID'])
//        ) {
//            Log::log(
//                'automation event failed to send one-off email',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->sendOneOffEmail',
//                [
//                    'identifier' => 'eventRunner::sendOneOffEmail',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return;
//        }
//
//        $companyID = $email['companyID'];
//
//        $dbEmail = LoadModel::emailDao()->get($companyID, $email['id']);
//        if (empty($dbEmail->isActive)) {
//            throw new RetryableAutomationException('email is inactive (runSendOneOffEmail)');
//        }
//
//        // Start from the DB email, but overwrite values because the workload can override things
//        $dbEmail->subject = $email['subject'];
//        $dbEmail->emailHTML = $email['emailHTML'] ?? $dbEmail->emailHTML;
//        $dbEmail->fromName = $email['fromName'];
//        $dbEmail->fromEmail = $email['fromEmail'];
//        $dbEmail->replyTo = $email['replyTo'];
//        $dbEmail->dynamicSubject = $email['dynamicSubject'];
//        $extraAttachmentURLs = !empty($email['attachments']) ? $email['attachments'] : [];
//
//        // if we are either sending a duplicate as a one off or the email
//        // permanently allows duplicate sends, send it.
//        $sendDuplicate = !empty($event['workflowEventData']['sendDuplicate']) || $dbEmail->allowDuplicateSend;
//
//        $eventIsSmartMail = $event['workflowEventData']['isSmartMail'] ?? false;
//
//        $companyProfile = LoadModel::companyProfile()->get($companyID);
//        $isMaFreeTrial = $companyProfile['isFromFreeTrial'];
//        if ($companyProfile['managedBy'] === $companyProfile['id'] && $companyProfile['isReseller']) {
//            $companyType = SendOptions::COMPANY_TYPE_AGENCY;
//        } elseif ($companyProfile['managedBy'] === $companyProfile['id'] && !$companyProfile['isReseller']) {
//            $companyType = SendOptions::COMPANY_TYPE_DIRECT_CLIENT;
//        } else {
//            $companyType = SendOptions::COMPANY_TYPE_AGENCY_CLIENT;
//        }
//
//        $offering = new Offering($companyProfile['productOffering']);
//        $isFreeOffering = $offering->hasOffering(Offering::FREE_OFFERING_MASK);
//        $primaryOffering = $offering->getPrimaryOffering();
//        $isFreeTrial = false;
//        if ($offering->hasOffering(Offering::PRO)) {
//            $freeTrialSvc = new FreeTrialService();
//            // TODO: cache this indefinitely
//            $status = $freeTrialSvc->getTrialStatus($companyID);
//            $isFreeTrial = $status['inActiveTrial'];
//        }
//
//        $options = new SendOptions('lead', [
//            'appendFooter'                  => false,
//            'sendDuplicate'                 => $sendDuplicate,
//            'workflowID'                    => null,
//            'authorID'                      => $event['triggerData']['userID'] ?? $event['workflowEventData']['authorID'] ?? null,
//            'useCustomSmtp'                 => $event['triggerData']['useCustomSmtp'] ?? null,
//            'suppressListUnsubscribeHeader' => $eventIsSmartMail,
//            'replaceAttachments'            => $eventIsSmartMail,
//            'isFreeOffering'                => $isFreeOffering,
//            'companyType'                   => $companyType,
//            'offering'                      => $primaryOffering,
//            'correlationID'                 => $event['workflowEventData']['correlationID'] ?? null,
//            'sendUnsubscribed'              => true,
//            'appendPoweredBy'               => !$event['workflowEventData']['isSmartMail'],
//            'isFreeTrial'                   => $isFreeTrial,
//        ]);
//        $recipientID = $event['whoID'];
//        if ($event['whoType'] === 'smartMailMulti') {
//            $recipient = new MultiSmartMailRecipient($companyID, $recipientID);
//        } else {
//            $recipient = new SingleLeadRecipient($companyID, $recipientID);
//        }
//
//        try {
//            $this->emailDelivery->sendEmailToLead($companyID, $dbEmail, $recipient, $options, $extraAttachmentURLs, $isMaFreeTrial);
//        } catch (RetryableAutomationException|RetryableMailshipException $e) {
//            $logLevel = $e->getCode() == Mailer::RESULT_FAILED_REDIS
//                ? LogEmailError::SILENT
//                : LogEmailError::NOTIFY;
//            $this->logEmailSendErrorAutomationEvent(
//                $companyID,
//                $options->emailJobID,
//                Mailer::RESULT_FAILED_MAILSHIP,
//                $e,
//                $logLevel,
//                $recipientID
//            );
//            throw new RetryableAutomationException('Error sending email to lead', 0, $e);
//        } catch (AutomationException $e) {
//            // catch just so that we re-throw so that it doesn't get caught below
//            throw $e;
//        } catch (MailshipException $e) {
//            $this->logEmailSendErrorAutomationEvent(
//                $companyID,
//                $options->emailJobID,
//                Mailer::RESULT_FAILED_MAILSHIP,
//                $e,
//                LogEmailError::NOTIFY,
//                $recipientID
//            );
//            throw new AutomationException($e->getMessage(), 0, $e);
//        } catch (RuntimeException $e) {
//            $this->logEmailSendErrorAutomationEvent(
//                $companyID,
//                $options->emailJobID,
//                Mailer::RESULT_FAILED,
//                $e,
//                LogEmailError::NOTIFY,
//                $recipientID
//            );
//            throw new RetryableAutomationException('runtime exception (runSendOneOffEmail)', 0, $e);
//        }
//    }
//
//    /**
//     * Assign a campaign to a lead
//     *
//     * @param $event
//     */
//    public function runAssignLeadCampaign($event)
//    {
//        $companyID = (int)$event['triggerData']['companyProfileID'];
//
//        //  SRSP-46853: Logging of assignments via workflow for 308495047
//        if ($companyID === 308495047) {
//            Log::info(
//                'assignLeadCampaign event',
//                'AutomationEventRunner->runAssignLeadCampaign',
//                [
//                    'companyID' => $companyID,
//                    'event'     => $event,
//                ],
//            );
//        }
//
//        if (isset($event['workflowEventData']['campaign']['id'])
//            && isset($event['workflowEventData']['workflowID'])
//            && isset($event['workflowEventData']['override'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $leadID = (int)$event['triggerData']['whoID'];
//            $campaignID = (int)$event['workflowEventData']['campaign']['id'];
//            $forcePrimary = (int)$event['workflowEventData']['override'];
//            $includeClosedOpp = $event['workflowEventData']['includeClosedOpp'] ?? false;
//            $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//            $result = LoadModel::leadCampaign()->save(
//                $companyID,
//                $leadID,
//                $campaignID,
//                LeadCampaignReason::automation($triggeredByWorkflowID),
//                $forcePrimary,
//                null,
//                null,
//                true,
//                $triggeredByWorkflowID,
//                $includeClosedOpp
//            );
//
//            if (!empty($result['failedLeadIDs'])) {
//                Log::error(
//                    'automation event failed to assign lead compaign',
//                    'AutomationEventRunner->runAssignLeadCampaign',
//                    [
//                        'identifier' => 'eventRunner::runAssignLeadCampaign',
//                        'message'    => 'function call failed to parse - failedLeadIDs',
//                        'details'    => json_encode($result['failedLeadIDs']),
//                    ]
//                );
//            }
//        } else {
//            Log::error(
//                'automation event failed to assign lead compaign',
//                'AutomationEventRunner->runAssignLeadCampaign',
//                [
//                    'identifier' => 'eventRunner::runAssignLeadCampaign',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Assign a lead owner
//     *
//     * Related function @param array $event
//     *
//     * @return bool|null
//     * @see AutomationEventRunner::runChangeLeadField()
//     *
//     */
//    public function runAssignLeadOwner($event)
//    {
//        $result = null;
//        $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['user'])
//            && isset($event['workflowEventData']['user']['companyProfileID'])
//            && isset($event['workflowEventData']['user']['id'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $override = $event['workflowEventData']['override'] ?? 0;
//            $leadID = $event['triggerData']['whoID'];
//            $ownerID = $event['workflowEventData']['user']['id'];
//            $companyID = $event['workflowEventData']['user']['companyProfileID'];
//
//            $lead = $this->leadModel->getByLeadID($leadID, $companyID);
//
//            if ($override || empty($lead['ownerID'])) {
//                $eventName = "owner changed by automation";
//                $result = $this->leadModel->assignToLeadOwner([$leadID], $ownerID, $companyID, false, $eventName, $triggeredByWorkflowID);
//                $this->leadModel->addAuditLogOwnerChange($companyID, $ownerID, $lead['ownerID'], $leadID, "automation", $triggeredByWorkflowID);
//            }
//
//            return $result;
//        } elseif (isset($event['companyProfileID'])
//            && isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['type'])
//            && $event['workflowEventData']['type'] == 'unassignOwner'
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $leadID = $event['triggerData']['whoID'];
//            $ownerID = 0;
//            $companyID = $event['companyProfileID'];
//
//            $lead = $this->leadModel->getByLeadID($leadID, $companyID);
//            $eventName = "owner removed by automation";
//
//            if (!empty($lead['ownerID'])) {
//                $result = $this->leadModel->assignToLeadOwner([$leadID], $ownerID, $companyID, false, $eventName, $triggeredByWorkflowID);
//                $this->leadModel->addAuditLogOwnerChange($companyID, $ownerID, $lead['ownerID'], $leadID, "automation", $triggeredByWorkflowID);
//            }
//
//            return $result;
//        } else {
//            Log::log(
//                'automation event failed to assign lead owner',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runAssignLeadOwner',
//                [
//                    'identifier' => 'eventRunner::runAssignLeadOwner',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return false;
//        }
//    }
//
//    /**
//     * Validate that the event has the proper info to be processed as a send notification.
//     * This function treats all companies with custom SMTP delivery as having invalid events.
//     *
//     * @param array $event
//     *
//     * @return bool - true if the event can be processed
//     */
//    private function validateSendNotificationEvent(array $event)
//    {
//        if (!isset($event['workflowEventData'], $event['triggerData'], $event['taskID'], $event['companyProfileID'], $event['whoID'])) {
//            Log::error('missing event data', 'AutomationEventRunner->runSendNotification', ['event' => $event]);
//
//            return false;
//        }
//
//        [
//            'companyProfileID' => $companyID,
//            'triggerData'      => $triggerData,
//        ] = $event;
//        $leadID = $triggerData['whoID'];
//        $lead = $this->leadModel->getByLeadID($leadID, $companyID);
//
//        if (!$lead) {
//            Log::error("invalid leadID: $leadID", 'AutomationEventRunner->runSendNotification', ['event' => $event]);
//
//            return false;
//        }
//
//        // Don't process these automation events if they are forced to use Custom SMTP.
//        // TODO: Support the use of Custom SMTP. (SRSP-38848 :alleg:)
//        $forceCustomSmtp = LoadLibrary::emailDelivery()->useCustomSmtp($companyID);
//        if ($forceCustomSmtp) {
//            return false;
//        }
//
//        return true;
//    }
//
//    /**
//     * Send a notification email to a user in the company's instance. This may change the userID if the workflow was
//     * intended to be sent to the leadOwner.
//     *
//     * @param int   $companyID
//     * @param int   $taskID
//     * @param int   $workflowID
//     * @param int   $userID
//     * @param array $triggerData
//     * @param array $workflowEvent
//     *
//     * @throws AutomationException
//     * @throws RetryableAutomationException
//     * @throws Exception
//     */
//    private function sendNotificationUser(int $companyID, int $taskID, int $workflowID, int $userID, array $triggerData, array $workflowEvent)
//    {
//        ['whoID' => $leadID] = $triggerData;
//        $lead = $this->leadModel->getByLeadID($leadID, $companyID);
//
//        //If we're set to send to lead owner, override the userID of the recipient
//        if (!empty($workflowEvent['leadOwner'])) {
//            if ($lead && $lead['ownerID']) {
//                $userID = $lead['ownerID'];
//            }
//        }
//
//        // by default send notifications to email. If text message is specified, augment default behavior
//        if (isset($workflowEvent['via']) && $workflowEvent['via'] == 'text') {
//            $textRecipient = new SingleUserRecipient($companyID, $userID, true);
//            $this->automationNotification->sendAutomationNotificationToUserPhone(
//                $companyID,
//                $textRecipient,
//                $taskID,
//                $triggerData
//            );
//        }
//
//        // send user notification
//        $recipient = new SingleUserRecipient($companyID, $userID);
//        if (!empty($workflowEvent['useCustomNotification'])) {
//            $success = $this->automationNotification->sendAutomationCustomNotificationToUser(
//                $companyID,
//                $recipient,
//                $workflowID,
//                $taskID,
//                $workflowEvent['customNotificationID'],
//                $triggerData
//            );
//            if (!$success) {
//                throw new RetryableAutomationException('custom runSendNotification failed');
//            }
//
//            return;
//        }
//
//        $success = $this->automationNotification->sendAutomationNotificationToUser(
//            $companyID,
//            $recipient,
//            $workflowID,
//            $taskID,
//            $triggerData,
//            '',
//            $workflowEvent
//        );
//        if (!$success) {
//            throw new RetryableAutomationException('non-custom runSendNotification failed');
//        }
//    }
//
//    /**
//     * Send a notification email to the specified emailAddress
//     *
//     * @param int         $companyID
//     * @param int         $taskID
//     * @param int         $workflowID
//     * @param string|null $emailAddress
//     * @param string|null $toReferrer
//     * @param array       $triggerData
//     * @param array       $workflowEvent
//     */
//    private function sendNotificationEmail(
//        int $companyID,
//        int $taskID,
//        int $workflowID,
//        ?string $emailAddress,
//        ?string $toReferrer,
//        array $triggerData,
//        array $workflowEvent
//    ) {
//        if ($toReferrer) {
//            // lookup referring leadID in mapping table and fetch lead
//            $referrer = $this->leadReferralModel->getReferrer($companyID, $triggerData['whoID']);
//
//            if ($referrer) {
//                $emailAddress = $referrer['emailAddress'];
//            }
//        }
//
//        if (empty($emailAddress)) {
//            $metadata = ['companyID' => $companyID, 'triggerData' => $triggerData];
//            Log::error('no email address found', 'automationeventrunner->sendNotificationEmail', $metadata);
//
//            return;
//        }
//
//        $emailRecipient = new MultiAddressRecipient($emailAddress);
//        if (!empty($workflowEvent['useCustomNotification'])) {
//            $this->automationNotification->sendAutomationCustomNotificationToEmail(
//                $companyID,
//                $emailRecipient,
//                $workflowID,
//                $taskID,
//                $workflowEvent['customNotificationID'],
//                $triggerData
//            );
//        } else {
//            $this->automationNotification->sendAutomationNotificationToEmail(
//                $companyID,
//                $emailRecipient,
//                $workflowID,
//                $taskID,
//                $triggerData,
//                '',
//                $workflowEvent
//            );
//        }
//    }
//
//    /**
//     * Send a notification email to either a user or an emailAddress
//     * Note: this also handles notifications originating from chatbot conversations
//     *
//     * @param array $event
//     *
//     * @throws Exception
//     */
//    public function runSendNotificationToUserOrEmail(array $event)
//    {
//        $isValid = $this->validateSendNotificationEvent($event);
//        if (!$isValid) {
//            return;
//        }
//
//        $companyID = $event['companyProfileID'];
//        $taskID = $event['taskID'] ?? null;
//
//        $triggerData = &$event['triggerData'];
//        $workflowEventData = &$event['workflowEventData'];
//        $workflowID = $workflowEventData['workflowID'] ?? null;
//
//        $userID = $workflowEventData['user']['id'] ?? null;
//        $emailAddress = $workflowEventData['emailAddress'] ?? null;
//        $toReferrer = $workflowEventData['toReferrer'] ?? null;
//
//        if (isset($workflowEventData['user']['id'])) {
//            // case 'sendNotification':
//            $this->sendNotificationUser(
//                (int)$companyID,
//                (int)$taskID,
//                (int)$workflowID,
//                (int)$userID,
//                $triggerData,
//                $workflowEventData
//            );
//        } else {
//            //  If email is to go to lead owner, look up email address
//            $toLeadOwner = ($workflowEventData['leadOwner'] === 'on');
//            if ($toLeadOwner && !empty($event['originatingLeadID'])) {
//                $ownerEmailAddress = $this->getLeadOwnerEmailAddress((int)$companyID, (int)$event['originatingLeadID']);
//                if (!empty($ownerEmailAddress)) {
//                    $emailAddress = $ownerEmailAddress;
//                } else {
//                    Log::log(
//                        'automation event failed to get lead owner email',
//                        Log::TYPE_ERROR,
//                        'AutomationEventRunner::runSendNotificationToUserOrEmail',
//                        [
//                            'identifier' => 'eventRunner::runSendNotificationToUserOrEmail',
//                            'message'    => 'no lead owner email address found',
//                            'details'    => json_encode($event),
//                        ]
//                    );
//                }
//            }
//
//            // case 'sendNotificationEmail':
//            // case 'sendNotificationEmailToReferrer':
//            $this->sendNotificationEmail(
//                (int)$companyID,
//                (int)$taskID,
//                (int)$workflowID,
//                $emailAddress,
//                $toReferrer,
//                $triggerData,
//                $workflowEventData
//            );
//        }
//    }
//
//    /**
//     * Remove the lead from membership in a workflow
//     *
//     * @param array $event
//     */
//    public function runRemoveFromWorkflow($event)
//    {
//        $companyID = $event['triggerData']['companyProfileID'] ?? false;
//        $workflowID = $event['workflowEventData']['workflow']['id'] ?? false;
//        $leadID = $event['triggerData']['whoID'] ?? false;
//        $exclude = !empty($event['workflowEventData']['exclude']);
//
//        if (!$companyID || !$workflowID || !$leadID) {
//            Log::log(
//                'automation event failed to remove from workflow',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runRemoveFromWorkflow',
//                [
//                    'identifier' => 'eventRunner::runRemoveFromWorkflow',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return;
//        }
//
//        //  Remove from main workflow branch
//        $this->workflowModel->removeMember($companyID, $workflowID, $leadID, $exclude);
//
//        //  Identify and remove from additional branches
//        $wfTree = (new VisualWorkflowLibrary())->getWorkflowTree($companyID, $workflowID);
//
//        //  Tasks
//        if (!empty($wfTree['primaryTaskID'])) {
//            foreach ($wfTree['tasks']['actionGroups'] ?? [] as $taskID => $task) {
//                if ((int)$taskID !== (int)$wfTree['primaryTaskID'] && !empty($task['requiredWorkflowID'])) {
//                    $this->workflowModel->removeMember($companyID, (int)$task['requiredWorkflowID'], $leadID, $exclude);
//                }
//            }
//        }
//
//        //  Action groups
//        if (!empty($wfTree['primaryActionGroupID'])) {
//            foreach ($wfTree['tasks']['actionGroups'] ?? [] as $groupID => $group) {
//                if ((int)$groupID !== (int)$wfTree['primaryActionGroupID']) {
//                    $this->workflowModel->removeMember($companyID, $groupID, $leadID, $exclude);
//                }
//            }
//        }
//    }
//
//    /**
//     * Remove the lead from membership in a Visual Workflow's workflows/"action groups"
//     *
//     * @param $event
//     */
//    public function runRemoveFromVisualWorkflow($event)
//    {
//        if (isset($event['workflowEventData']['workflow']['id'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $exclude = !empty($event['workflowEventData']['exclude']);
//            $visualWorkflowID = $event['workflowEventData']['workflow']['id'];
//            $companyID = $event['triggerData']['companyProfileID'];
//            $leadID = $event['triggerData']['whoID'];
//            $actionGroups = LoadModel::automationTaskWorkflows()->getByVisualWorkflowID($companyID, $visualWorkflowID);
//
//            foreach ($actionGroups as $actionGroup) {
//                $this->workflowModel->removeMember($companyID, $actionGroup['id'], $leadID, $exclude);
//                // additionally, remove lead from all workflows this workflow could possibly have added them to.
//                $actionGroupEvents = $this->workflowEventModel->getByWorkflowID($companyID, $actionGroup['id']);
//                foreach ($actionGroupEvents as $actionGroupEvent) {
//                    if ($actionGroupEvent['eventType'] == 'addToWorkflow'
//                        && isset($actionGroupEvent['data'])
//                        && isset($actionGroupEvent['data']['addToActionGroupID'])
//                    ) {
//                        $childActionGroupID = $actionGroupEvent['data']['addToActionGroupID'];
//                        $this->workflowModel->removeMember($companyID, $childActionGroupID, $leadID, $exclude);
//                    }
//                }
//            }
//        } else {
//            Log::log(
//                'automation event failed to remove lead from visual workflow',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runRemoveFromVisualWorkflow',
//                [
//                    'identifier' => 'eventRunner::runRemoveFromVisualWorkflow',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Change a lead's field value for the given field
//     *
//     * @param $event
//     *
//     * @return bool|void
//     * @throws Exception
//     */
//    public function runChangeLeadField($event)
//    {
//        // Note: We're using array_key_exists in the below conditional
//        // because $event['workflowEventData']['value'] can have a value of null
//        // and still be valid. isset() returns false when it encounters null
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['fieldID'])
//            && array_key_exists('value', $event['workflowEventData'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $fieldID = $event['workflowEventData']['fieldID'];
//            $label = $event['workflowEventData']['label'];
//            $value = $event['workflowEventData']['value'];
//            $leadID = $event['triggerData']['whoID'];
//            $dataType = !empty($event['workflowEventData']['dataType']) ? $event['workflowEventData']['dataType'] : null;
//            $override = !empty($event['workflowEventData']['override']);
//
//            $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//            // Don't allow people to re-subscribe people via the automation engine.
//            if ($label && $label === 'Is Unsubscribed' && (int)$value === 0) {
//                return false;
//            }
//
//            // Don't modify gdprConsent via automation
//            if ($label && $label === 'GDPR Consent') {
//                return false;
//            }
//
//            // Replace slashes in datetime with dashes
//            if ($dataType === 'datetime') {
//                $value = str_replace('/', '-', $value);
//            }
//
//            // Appending new checkbox values instead of overwriting needs some different handling
//            if ($dataType === 'checkbox' && !$override) {
//                $this->leadFieldValue->appendCheckboxValueForLead($companyID, $fieldID, $leadID, $value, true, 0, $triggeredByWorkflowID);
//
//                return;
//            }
//
//            $subscriptionSource = \SS\Models\LeadConfirmation_model::SOURCE_AUTOMATION;
//            if ($label === 'Is Unsubscribed') {
//                $subscriptionSource = \SS\Models\Event\LeadSubscriptionHistory::AUTOMATION;
//            }
//
//            // When isQualified, isContact or isCustomer is changed, the lead status may also
//            // need to be updated to keep it consistent.
//            $field = LoadModel::field()->get($companyID, $fieldID);
//            if ($field
//                && ($field['systemName'] === 'isQualified'
//                    || $field['systemName'] === 'isContact'
//                    || $field['systemName'] === 'isCustomer')
//            ) {
//                $oldLeadData = $this->leadModel->getByLeadID($leadID, $companyID);
//                $statusFlags = new StatusFlags(
//                    $field['systemName'] === 'isQualified' ?
//                        ($value ?: null) : $oldLeadData['isQualified'],
//                    $field['systemName'] === 'isContact' ? $value : $oldLeadData['isContact'],
//                    $field['systemName'] === 'isCustomer' ? $value : $oldLeadData['isCustomer']
//                );
//                $statusObj = Status::getStatusFromFlags($statusFlags);
//                $updateData = [
//                    'isQualified' => $statusFlags->isQualified,
//                    'isContact'   => $statusFlags->isContact,
//                    'isCustomer'  => $statusFlags->isCustomer,
//                    'status'      => $statusObj->status,
//                ];
//                if ($leadID) {
//                    $previousStatus = $oldLeadData['status'];
//
//                    if ($previousStatus != $statusObj->status) {
//                        // Only make a "change" (and therefore trigger) if status actually changes
//                        $this->leadModel->update(
//                            $companyID,
//                            $updateData,
//                            ['id' => $leadID],
//                            false,
//                            true,
//                            null,
//                            null,
//                            $triggeredByWorkflowID
//                        );
//                    }
//
//                    $leadStatusHistoryDao = new \Coil\Lead\Struct\LeadStatusHistoryDao();
//                    $leadStatusHistoryDao->insertLeadStatusHistory(
//                        $companyID,
//                        $leadID,
//                        $event['workflowID'],
//                        $previousStatus,
//                        $statusObj->status
//                    );
//                }
//            } else {
//                $this->leadFieldValue->setValueForLead(
//                    $companyID,
//                    $fieldID,
//                    $leadID,
//                    $value,
//                    false,
//                    true,
//                    0,
//                    $subscriptionSource,
//                    0,
//                    $triggeredByWorkflowID
//                );
//            }
//            LeadScoring::setStale($companyID, $leadID, LeadScoring::PROFILE_SCORE_STALE);
//        } else {
//            Log::log(
//                'automation event failed to change lead field',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runChangeLeadField',
//                [
//                    'identifier' => 'eventRunner::runChangeLeadField',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Change the opportunity status for an opportunity (part of an opportunity workflow)
//     *
//     * @param $event
//     *
//     * @throws Exception
//     */
//    public function runChangeOpportunityStatus($event)
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['status'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || !isset($event['triggerData']['whoID'])
//            || !isset($event['triggerData']['whoType'])
//            || $event['triggerData']['whoType'] !== 'lead'
//        ) {
//            Log::log(
//                'automation event failed to change opportunity status',
//                Log::TYPE_ERROR,
//                'automation',
//                [
//                    'identifier' => 'eventRunner::runChangeOpportunityStatus',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $fieldModel = LoadModel::field();
//        $companyID = $event['triggerData']['companyProfileID'];
//        $triggerOppID = !empty($event['triggerData']['whatType']) && $event['triggerData']['whatType'] === 'opportunity'
//            ?
//            $event['triggerData']['whatID']
//            :
//            null;
//        $workflowOppID = $event['whatType'] === 'opportunity' && !empty($event['whatID']) ? $event['whatID'] : null;
//        [$isWonFieldID, $isClosedFieldID] = $fieldModel->getFieldIDsByName($companyID, ['isWon', 'isClosed'], 'opportunity');
//        $status = $event['workflowEventData']['status'];
//        switch ($status) {
//            case 'closedWon':
//                $isWon = 1;
//                $isClosed = 1;
//                break;
//            case 'closedLost':
//                $isWon = 0;
//                $isClosed = 1;
//                break;
//            case 'open':
//            default:
//                $isWon = 0;
//                $isClosed = 0;
//        }
//
//        $event['multipleOppsAction'] = 'noAction';
//
//        $event['workflowEventData']['fieldID'] = $isWonFieldID;
//        $event['workflowEventData']['value'] = $isWon;
//
//        $this->runChangeOpportunityField($event);
//
//        $event['workflowEventData']['fieldID'] = $isClosedFieldID;
//        $event['workflowEventData']['value'] = $isClosed;
//
//        $this->runChangeOpportunityField($event);
//
//        $dealStageModel = LoadModel::dealStage();
//        $dealStage = [];
//        if (!empty($triggerOppID) || !empty($workflowOppID)) {
//            $opp = $this->opportunityModel->get($companyID, $triggerOppID ?? $workflowOppID);
//            if ($isClosed) {
//                //  Update closeDate if opp was closed...
//                $this->opportunityModel->parentUpdate(
//                    $companyID,
//                    $opp['id'],
//                    [
//                        'closeDate' => $this->getCurrentDate($companyID, 'datetime'),
//                    ]
//                );
//            }
//            if (!empty($opp['dealStageID'])) {
//                $dealStage = $dealStageModel->get($companyID, $opp['dealStageID']);
//            }
//        } else {
//            if ($isClosed) {
//                Log::log(
//                    'Should be setting opportunity.closeDate but no opp ID',
//                    Log::TYPE_DEBUG,
//                    'AutomationEventRunner::runChangeOpportunityStatus',
//                    [
//                        'event'    => $event,
//                        'isClosed' => $isClosed,
//                        'isWon'    => $isWon,
//                        'status'   => $status,
//                    ]
//                );
//            }
//        }
//
//        // We must convert the lead to a "customer" status if an opp was won
//        if ($status === 'closedWon' && (empty($dealStage['pipelineType']) || $dealStage['pipelineType'] === 'sales')) {
//            LoadModel::lead()->setLeadIsCustomer($companyID, $event['triggerData']['whoID']);
//        }
//    }
//
//    /**
//     * Assign an owner to an opportunity (part of an opportunity workflow)
//     *
//     * @param $event
//     *
//     * @throws Exception
//     */
//    public function runAssignOpportunityOwner($event)
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['user'])
//            || !isset($event['workflowEventData']['user']['companyProfileID'])
//            || !isset($event['workflowEventData']['user']['id'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['whoID'])
//        ) {
//            Log::log(
//                'automation event failed to assign lead owner',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runAssignLeadOwner',
//                [
//                    'identifier' => 'eventRunner::runAssignLeadOwner',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $ownerID = $event['workflowEventData']['user']['id'];
//
//        $fieldModel = LoadModel::field();
//        $companyID = $event['triggerData']['companyProfileID'];
//        [$ownerIDFieldID] = $fieldModel->getFieldIDsByName($companyID, ['ownerID'], 'opportunity');
//
//        $event['workflowEventData']['multipleOppsAction'] = 'noAction';
//        $event['workflowEventData']['fieldID'] = $ownerIDFieldID;
//        $event['workflowEventData']['value'] = $ownerID;
//
//        $this->runChangeOpportunityField($event);
//    }
//
//    /**
//     * Change an opportunity's field value for the given field (part of an opportunity workflow)
//     *
//     * @param $event
//     */
//    public function runChangeOpportunityField($event)
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['fieldID'])
//            || !isset($event['workflowEventData']['value'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || !isset($event['triggerData']['whoID'])
//            || !isset($event['triggerData']['whoType'])
//            || $event['triggerData']['whoType'] !== 'lead'
//        ) {
//            Log::log(
//                'automation event failed to change opportunity field',
//                Log::TYPE_ERROR,
//                'automation',
//                [
//                    'identifier' => 'eventRunner::runChangeOpportunityField',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $fieldID = $event['workflowEventData']['fieldID'];
//        $value = $event['workflowEventData']['value'];
//        $pipelineID = $event['workflowEventData']['pipelineID'] ?? null;
//        $override = !empty($event['workflowEventData']['override']);
//        $companyID = $event['triggerData']['companyProfileID'];
//        $triggerOppID = !empty($event['triggerData']['whatType']) && $event['triggerData']['whatType'] === 'opportunity'
//            ?
//            $event['triggerData']['whatID']
//            :
//            null;
//        $workflowOppID = $event['whatType'] === 'opportunity' && !empty($event['whatID']) ? $event['whatID'] : null;
//
//        $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//        $field = LoadModel::field()->get($companyID, $fieldID);
//        if (!$field) {
//            Log::log(
//                'automation event failed to change opportunity field',
//                Log::TYPE_ERROR,
//                'automation',
//                [
//                    'identifier' => 'eventRunner::runChangeOpportunityField',
//                    'message'    => 'field is missing',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//        if ($field['systemName'] === 'importLink'
//            || $field['systemName'] === 'isActive'
//            || $field['systemName'] === 'ownerEmailAddress'
//        ) {
//            return;
//        }
//
//        // Some fields cannot be set to empty values, so we stop if the value is empty
//        $nonEmptyFields = [
//            'ownerID'         => 1,
//            'opportunityName' => 1,
//            'amount'          => 1,
//            'dealStage'       => 1,
//            'closeDate'       => 1,
//        ];
//        if (!empty($nonEmptyFields[$field['systemName']]) && empty($value)) {
//            return;
//        }
//
//        $dataType = $field['dataType'];
//        // Replace slashes in datetime with dashes
//        if ($dataType === 'datetime') {
//            $value = str_replace('/', '-', $value);
//        }
//
//        if (!empty($triggerOppID)) {
//            // if a specific opp triggered this workflow, use it
//            $opps = [$this->opportunityModel->get($companyID, $triggerOppID)];
//        } elseif (!empty($workflowOppID)) {
//            // This is the opp that succeeded all the automation filters for opp workflows
//            $opps = [$this->opportunityModel->get($companyID, $workflowOppID)];
//        }
//
//        if (!empty($pipelineID)) {
//            // Filter opps to ones that are in the given pipeline
//            $opps = array_filter($opps, function($opp) use ($pipelineID) {
//                return $opp['pipelineID'] == $pipelineID;
//            });
//        }
//        if (count($opps) !== 1) {
//            return;
//        }
//        $opp = reset($opps);
//
//        // Appending new checkbox values instead of overwriting needs some different handling
//        if ($dataType === 'checkbox' && !$override) {
//            $this->opportunityFieldValueModel->appendCheckboxValueForOpportunity($companyID, $fieldID, $opp['id'], $value, $triggeredByWorkflowID);
//
//            return;
//        }
//
//        if ($field['systemName'] === 'dealStage') {
//            $runDepth = $event['workflowEventData']['runDepth'] ?? 0;
//            $this->opportunityModel->setDealStage(
//                $companyID,
//                $opp['id'],
//                $opp['probability'],
//                $value,
//                $opp['dealStageID'],
//                $runDepth,
//                $event['workflowID']
//            );
//        } elseif (empty($field['isCustom'])) {
//            if (($field['dataType'] === 'date' || $field['dataType'] === 'datetime') && $value === 'NOW') {
//                $value = $this->getCurrentDate($companyID, $field['dataType']);
//            }
//            $update = [$field['systemName'] => $value];
//            $this->opportunityModel->parentUpdate($companyID, $opp['id'], $update);
//        } else {
//            $this->opportunityFieldValueModel->setValueForOpportunity(
//                $companyID,
//                $fieldID,
//                $opp['id'],
//                $value,
//                true,
//                $triggeredByWorkflowID
//            );
//        }
//    }
//
//    /**
//     * Get the current datetime in Y-m-d H:is format, using the sales timezone from the company's settings
//     *
//     * @param int    $companyID
//     * @param string $dataType
//     *
//     * @return string
//     * @throws Exception
//     */
//    private function getCurrentDate(int $companyID, string $dataType)
//    {
//        $value = '';
//        $companyProfile = LoadModel::companyProfile()->get($companyID);
//        $companyTimezone = new DateTimeZone($companyProfile['salesOfficialTimezone']);
//        $date = new DateTime();
//        $date->setTimezone($companyTimezone);
//        if ($dataType === 'datetime') {
//            $value = $date->format('Y-m-d H:i:s');
//        } elseif ($dataType === 'date') {
//            $value = $date->format('Y-m-d 12:00:00');
//        }
//
//        return $value;
//    }
//
//    /**
//     * Change the persona for a lead
//     *
//     * @param $event
//     *
//     * @throws Exception
//     */
//    public function runChangeLeadPersona($event)
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['fieldID'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $personaID = $event['workflowEventData']['fieldID'];
//            $leadID = $event['triggerData']['whoID'];
//            $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//            LoadModel::personaLead()->setPersonaByPersonaID($companyID, $leadID, $personaID, true, $triggeredByWorkflowID);
//        } else {
//            Log::log(
//                'automation event failed to change lead persona',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runChangeLeadField',
//                [
//                    'identifier' => 'eventRunner::runChangeLeadPersona',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Increment a lead's field value for a given numeric field
//     *
//     * @param $event
//     */
//    public function runIncrementCounterField($event)
//    {
//        $this->modifyCounterField('increment', $event);
//    }
//
//    /**
//     * Decrement a lead's field value for a given numeric field
//     *
//     * @param $event
//     */
//    public function runDecrementCounterField($event)
//    {
//        $this->modifyCounterField('decrement', $event);
//    }
//
//    /**
//     * Used by runIncrementalCounterField and runDecrementCounterField for modifying a lead's numeric fields
//     *
//     * @param $action
//     * @param $event
//     *
//     * @return bool
//     */
//    public function modifyCounterField($action, $event)
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['fieldID'])
//            && isset($event['workflowEventData']['amount'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $fieldID = $event['workflowEventData']['fieldID'];
//            $amount = $event['workflowEventData']['amount'];
//            $leadID = $event['triggerData']['whoID'];
//            $label = $event['workflowEventData']['label'];
//            $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//            // TODO: should we pass a valid numeric value?
//            $source = null;
//
//            // Is Unsubscribed and GDPR shouldn't be used as a counter field
//            if ($label && ($label === 'Is Unsubscribed' || $label === 'GDPR Consent')) {
//                return false;
//            }
//
//            $currentValue = (int)$this->leadFieldValue->getValueForLead($companyID, $fieldID, $leadID) ?: 0;
//
//            if ($action == 'increment') {
//                $newValue = $currentValue + $amount;
//            } elseif ($action == 'decrement') {
//                $newValue = $currentValue - $amount;
//            }
//
//            $this->leadFieldValue->setValuesForLead(
//                $companyID,
//                $leadID,
//                [$fieldID => $newValue],
//                false,
//                true,
//                false,
//                $source,
//                0,
//                $triggeredByWorkflowID
//            );
//        } else {
//            Log::log(
//                'automation event failed to modify counter field',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->modifyCounterField',
//                [
//                    'identifier' => 'eventRunner::modifyCounterField',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Add a lead to a list
//     *
//     * @param $event
//     */
//    public function runAddToList($event)
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['listID'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $listID = $event['workflowEventData']['listID'];
//            $leadID = $event['triggerData']['whoID'];
//
//            if (!$this->isSystemList((int)$companyID, (int)$listID)) {
//                $this->listModel->insertMember($companyID, $listID, $leadID, 'lead');
//            } else {
//                Log::debug(
//                    'Not adding member to system list',
//                    'AutomationEventRunner::runAddToList',
//                    ['event' => $event]
//                );
//            }
//        } else {
//            Log::log(
//                'automation event failed to add to list',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->addToList',
//                [
//                    'identifier' => 'eventRunner::addToList',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Add a lead to all lists with a given tag
//     *
//     * @param $event
//     */
//    public function runAddToListsWithTag($event)
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['tagID'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $tagID = $event['workflowEventData']['tagID'];
//            $leadID = $event['triggerData']['whoID'];
//
//            $lists = $this->listModel->getAllByTag($companyID, $tagID);
//
//            foreach ($lists as $list) {
//                $listID = $list['objectID'];
//                if (!$this->isSystemList((int)$companyID, (int)$listID)) {
//                    $this->listModel->insertMember($companyID, $listID, $leadID, 'lead');
//                } else {
//                    Log::debug(
//                        'Not adding member to system list with tag "' . $tagID . '"',
//                        'AutomationEventRunner::runAddToListWithTag',
//                        ['event' => $event]
//                    );
//                }
//            }
//        } else {
//            Log::log(
//                'automation event failed to add to list with tag',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->addToListWithTag',
//                [
//                    'identifier' => 'eventRunner::addToListWithTag',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Remove a lead from a list
//     *
//     * @param $event
//     */
//    public function runRemoveFromList($event)
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['listID'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $listID = $event['workflowEventData']['listID'];
//            $leadID = $event['triggerData']['whoID'];
//
//            if (!$this->isSystemList((int)$companyID, (int)$listID)) {
//                $this->listModel->removeMemberByLeadID($companyID, $listID, $leadID);
//            } else {
//                Log::debug(
//                    'Not removing member from system list',
//                    'AutomationEventRunner::runRemoveFromList',
//                    ['event' => $event]
//                );
//            }
//        } else {
//            Log::log(
//                'automation event failed to remove from list',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->removeFromlist',
//                [
//                    'identifier' => 'eventRunner::removeFromlist',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Remove a lead from all lists with a given tag
//     *
//     * @param $event
//     */
//    public function runRemoveFromListsWithTag($event)
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['tagID'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $tagID = $event['workflowEventData']['tagID'];
//            $leadID = $event['triggerData']['whoID'];
//
//            $lists = $this->listModel->getAllByTag($companyID, $tagID);
//            foreach ($lists as $list) {
//                $listID = $list['objectID'];
//                if (!$this->isSystemList((int)$companyID, (int)$listID)) {
//                    $this->listModel->removeMemberByLeadID($companyID, $listID, $leadID);
//                } else {
//                    Log::debug(
//                        'Not removing member from system list with tag',
//                        'AutomationEventRunner::runRemoveFromListWithTag',
//                        ['event' => $event]
//                    );
//                }
//            }
//        } else {
//            Log::log(
//                'automation event failed to remove from list with tag',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->removeFromListWithTag',
//                [
//                    'identifier' => 'eventRunner::removeFromListWithTag',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//    }
//
//    /**
//     * Change a lead's status and record the change
//     *
//     * @param $event
//     *
//     * @return bool
//     * @throws Exception
//     */
//    public function runChangeLeadStatus($event): bool
//    {
//        if (isset($event['workflowEventData'])
//            && isset($event['workflowEventData']['status'])
//            && isset($event['triggerData'])
//            && isset($event['triggerData']['companyProfileID'])
//            && isset($event['triggerData']['whoID'])
//        ) {
//            $companyID = $event['triggerData']['companyProfileID'];
//            $status = $event['workflowEventData']['status'];
//            $leadID = $event['triggerData']['whoID'];
//            $triggeredByWorkflowID = $event['workflowID'] ?? null;
//
//            $statusObj = Status::getStatusFromName($status);
//            $statusFlags = Status::getStatusFlags($statusObj);
//            $updateData = [
//                'isQualified' => $statusFlags->isQualified,
//                'isContact'   => $statusFlags->isContact,
//                'isCustomer'  => $statusFlags->isCustomer,
//                'status'      => $statusObj->status,
//            ];
//
//            if ($leadID && $statusObj) {
//                $oldLeadData = $this->leadModel->getByLeadID($leadID, $companyID);
//                $previousStatus = $oldLeadData['status'];
//
//                if ($previousStatus != $statusObj->status) {
//                    // Only make a "change" (and therefore trigger) if status actually changes
//                    $this->leadModel->update(
//                        $companyID,
//                        $updateData,
//                        ['id' => $leadID],
//                        false,
//                        true,
//                        null,
//                        null,
//                        $triggeredByWorkflowID
//                    );
//                }
//
//                $leadStatusHistoryDao = new \Coil\Lead\Struct\LeadStatusHistoryDao();
//                $leadStatusHistoryDao->insertLeadStatusHistory(
//                    $companyID,
//                    $leadID,
//                    $event['workflowID'],
//                    $previousStatus,
//                    $statusObj->status
//                );
//
//                return true;
//            }
//        }
//        Log::log(
//            'automation event failed to change lead status',
//            Log::TYPE_ERROR,
//            'AutomationEventRunner->runChangeLeadStatus',
//            [
//                'identifier' => 'eventRunner::runChangeLeadStatus',
//                'message'    => 'function call failed to parse',
//                'details'    => json_encode($event),
//            ]
//        );
//
//        return false;
//    }
//
//    /**
//     * Queue sending a "postBack" to a URL with a lead's information
//     *
//     * Note: There is a dedicated repo for the `postback` service:
//     *  https://github.com/sharpspring/postback
//     *
//     * @param $event
//     */
//    public function runPostBackLead($event)
//    {
//        if (empty($event['workflowEventData']['url'])
//            || empty($event['triggerData']['companyProfileID'])
//            || empty($event['triggerData']['whoID'])
//        ) {
//            Log::log(
//                'automation event failed to post back lead',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runPostBackLead',
//                [
//                    'identifier' => 'eventRunner::runPostBackLead',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return;
//        }
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $url = $event['workflowEventData']['url'];
//        $leadID = $event['triggerData']['whoID'];
//
//        $lead = $this->leadModel->get($companyID, $leadID);
//        if (empty($lead)) {
//            Log::log(
//                'automation event failed to post back lead',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runPostBackLead',
//                [
//                    'identifier' => 'eventRunner::runPostBackLead',
//                    'message'    => 'empty lead',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return;
//        }
//
//        $lead['leadStatus'] = Lead::getStatusNameFromPayload($lead);
//        $customFields = $this->leadFieldValue->getLeadCustomFieldValues($companyID, $lead['id']);
//        $lead = array_merge($lead, $customFields);
//        $lead['leadID'] = $lead['id'];
//
//        // send the postback via a dedicated queue
//        foreach ($lead as $key => $value) {
//            $lead[$key] = (string)$value;
//        }
//        $workload = [
//            'isWorkflowEvent' => 1,
//            'url'             => $url,
//            'post'            => $lead,
//        ];
//        LoadLibrary::gearman()->addJob($companyID, 'postback', $workload);
//    }
//
//    /**
//     * Queue sending a "postBack" to a URL with an opportunity's information
//     *
//     * Note: There is a dedicated repo for the `postback` service:
//     *  https://github.com/sharpspring/postback
//     *
//     * @param $event
//     */
//    public function runPostBackOpportunity($event)
//    {
//        if (empty($event['workflowEventData']['url'])
//            || !isset($event['workflowEventData']['oppPrimary'])
//            || empty($event['triggerData']['companyProfileID'])
//            || empty($event['whatID'])
//            || $event['whatType'] !== 'opportunity'
//        ) {
//            Log::log(
//                'automation event failed to post back opportunity',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runPostBackOpportunity',
//                [
//                    'identifier' => 'eventRunner::runPostBackOpportunity',
//                    'message'    => 'function call failed to parse, missing required fields',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return;
//        }
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $url = $event['workflowEventData']['url'];
//        $opportunityID = $event['whatID'];
//        $oppPrimary = !empty($event['workflowEventData']['oppPrimary']);
//
//        $opportunity = $this->opportunityModel->get($companyID, $opportunityID);
//        if (empty($opportunity)) {
//            Log::log(
//                'automation event failed to post back opportunity',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runPostBackOpportunity',
//                [
//                    'identifier' => 'eventRunner::runPostBackOpportunity',
//                    'message'    => 'empty opportunity',
//                    'details'    => json_encode($event),
//                ]
//            );
//
//            return;
//        }
//
//        // get opp custom fields
//        $oppCustomFields = $this->opportunityFieldValueModel->getValuesForOpportunities($companyID, [$opportunityID])[$opportunityID] ?? [];
//        $customFields = reKey($this->field->getByIDs($companyID, array_keys($oppCustomFields)), 'id');
//        if (!empty($customFields)) {
//            foreach ($customFields as $fieldID => $field) {
//                $opportunity[$field['systemName']] = $oppCustomFields[$fieldID]['textValue'];
//            }
//        }
//
//        // get opp primary or all contact data (id, email, first-last name)
//        if ($oppPrimary) {
//            $primaryLead = $this->leadModel->get($companyID, $opportunity['primaryLeadID'], ['firstName', 'lastName', 'emailAddress']);
//            $opportunity['primaryLeadFirstName'] = $primaryLead['firstName'];
//            $opportunity['primaryLeadLastName'] = $primaryLead['lastName'];
//            $opportunity['primaryLeadDisplayName'] = trim($primaryLead['firstName'] . ' ' . $primaryLead['lastName']);
//            $opportunity['primaryLeadEmailAddress'] = $primaryLead['emailAddress'];
//        } else {
//            $oppLeadIDs = array_keys(reKey($this->opportunityModel->getLeadsForOpp($companyID, $opportunityID), 'leadID'));
//            $oppLeads = $this->leadModel->getWhereIn($companyID, $oppLeadIDs);
//            for ($i = 0; $i < count($oppLeads); $i++) {
//                $lead = $oppLeads[$i];
//                $prefix = 'contactLead_' . ($i + 1);
//                $opportunity["{$prefix}_id"] = $lead['id'];
//                $opportunity["{$prefix}_firstName"] = $lead['firstName'];
//                $opportunity["{$prefix}_lastName"] = $lead['lastName'];
//                $opportunity["{$prefix}_displayName"] = trim($lead['firstName'] . ' ' . $lead['lastName']);
//                $opportunity["{$prefix}_emailAddress"] = $lead['emailAddress'];
//            }
//        }
//
//        // get opp owner data (id, email, first-last name)
//        if (!empty($opportunity['ownerID'])) {
//            $oppOwner = $this->userProfileModel->get($companyID, $opportunity['ownerID']);
//            $opportunity['ownerFirstName'] = $oppOwner['firstName'];
//            $opportunity['ownerLastName'] = $oppOwner['lastName'];
//            $opportunity['ownerDisplayName'] = $oppOwner['displayName'];
//            $opportunity['ownerEmailAddress'] = $oppOwner['emailAddress'];
//        }
//
//        LoadLibrary::postback()->queuePostback($opportunity, $url);
//    }
//
//    /**
//     * Queue adding a lead to a workflow/"action group"
//     *
//     * @param      $event
//     * @param bool $testing
//     *
//     * @return array
//     */
//    public function runAddToActionGroup($event, $testing = false)
//    {
//        if (!isset($event['workflowEventData']['addToActionGroupID'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || !isset($event['triggerData']['whoID'])
//        ) {
//            Log::log(
//                'automation event failed to add to action group',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runAddToActionGroup',
//                [
//                    'identifier' => 'eventRunner::runAddToActionGroup',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException('eventRunner::runAddToActionGroup call failed to parse', 0);
//        }
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $leadID = $event['triggerData']['whoID'];
//        $actionGroupID = $event['workflowEventData']['addToActionGroupID'];
//        $actionGroup = $this->workflowModel->get($companyID, $actionGroupID);
//        $workflowEvents = $this->workflowEventModel->getByWorkflowID($companyID, $actionGroupID);
//
//        $workflowEvents = array_map(function($workflowEvent) use ($event) {
//            // these action types need to know the source workflow/task in order to build
//            // a proper subject and body for the notification email. So we need
//            // to pass along that information in this step.
//            switch ($workflowEvent['eventType']) {
//                case 'sendNotificationEmail':
//                case 'sendNotification':
//                    $workflowEvent['sourceWorkflowID'] = $event['workflowID'];
//                    $workflowEvent['sourceTaskID'] = $event['taskID'];
//                    break;
//                case 'sendEmail':
//                    //  remove html cuz it's too big and unused
//                    unset($workflowEvent['data']['email']['emailHTML']);
//            }
//
//            return $workflowEvent;
//        }, $workflowEvents);
//
//        // since we're adding, increment the runDepth
//        $runDepth = ($event['workflowEventData']['runDepth'] ?? 0) + 1;
//        // Pass the runDepth along in the job; in the event that the next workflow that we're scheduling also includes an 'addToActionGroup' event,
//        // we'll pull that runDepth and pass it along, so that it will get incremented again. Max depth = 5 currently, and is enforced in eventQueue->doWorkflowEvent
//        $workload = [
//            'companyID'             => $companyID,
//            'listID'                => null,
//            'leadID'                => $leadID,
//            'workflowID'            => $actionGroupID,
//            'isRepeatable'          => $actionGroup['isRepeatable'],
//            'workflowEvents'        => $workflowEvents,
//            'time'                  => time(),
//            'limit'                 => null,
//            'offset'                => 0,
//            'runDepth'              => $runDepth,
//            'runOnAllLeads'         => true,
//            'scheduledByAutomation' => true,
//        ];
//
//        if (!empty($event['triggerData'])) {
//            $workload['triggerData'] = $event['triggerData'];
//        }
//
//        if ($testing) {
//            // TODO: Write tests for this -- needs a lot of data in place, and there's basically no documentation about how that data has to be structured
//            // TODO: add documentation about the automation engine (NOTE: A lot of the inline comments are inaccurate, or outdated)
//            return $workload;
//        }
//
//        LoadLibrary::mqJobQueue()->addJob($companyID, 'doScheduleWorkflow', $workload);
//    }
//
//    /**
//     * Queue add a lead to a visual workflow and any action groups for that visual workflow
//     *
//     * @param      $event
//     * @param bool $testing
//     *
//     * @return array
//     * @throws Exception
//     */
//    public function runAddToVisualWorkflow($event, $testing = false)
//    {
//        if (!isset($event['workflowEventData']['visualWorkflowID'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || !isset($event['triggerData']['whoID'])
//        ) {
//            Log::log(
//                'automation event failed to add to visual workflow',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runAddToVisualWorkflow',
//                [
//                    'identifier' => 'eventRunner::runAddToVisualWorkflow',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException('eventRunner::runAddToVisualWorkflow call failed to parse', 0);
//        }
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $leadID = $event['triggerData']['whoID'];
//        $visualWorkflowID = $event['workflowEventData']['visualWorkflowID'];
//        $visualWorkflow = \SS\Models\Automation\VisualWorkflow::get($companyID, $visualWorkflowID);
//        // increment the runDepth
//        $runDepth = $event['workflowEventData']['runDepth'] + 1;
//        if (empty($visualWorkflow)) {
//            Log::log('unable to locate visual workflow', Log::TYPE_ERROR, 'automation', [
//                'visualWorkflowID' => $visualWorkflowID,
//                'companyID'        => $companyID,
//            ]);
//            throw new AutomationException('unable to locate visual workflow' . $visualWorkflowID, 0);
//        }
//
//        $primaryActionGroupID = $visualWorkflow->getPrimaryActionGroupID();
//        if (!$primaryActionGroupID) {
//            Log::log('invalid visual workflow', Log::TYPE_ERROR, 'automation', [
//                'visualWorkflowID' => $visualWorkflowID,
//                'companyID'        => $companyID,
//            ]);
//            throw new AutomationException('Invalid visual workflow' . $visualWorkflowID, 0);
//        }
//
//        $actionGroup = $this->workflowModel->get($companyID, $primaryActionGroupID);
//        $workflowEvents = $this->workflowEventModel->getByWorkflowID($companyID, $primaryActionGroupID);
//        // Pass the runDepth along in the job; in the event that the next workflow that we're scheduling also includes an 'addToActionGroup' event,
//        // we'll pull that runDepth and pass it along, so that it will get incremented again. Max depth = 5 currently, and is enforced in eventQueue->doWorkflowEvent
//        $workload = [
//            'companyID'      => $companyID,
//            'listID'         => null,
//            'leadID'         => $leadID,
//            'workflowID'     => $primaryActionGroupID,
//            'isRepeatable'   => $actionGroup['isRepeatable'],
//            'workflowEvents' => $workflowEvents,
//            'time'           => time(),
//            'limit'          => null,
//            'offset'         => 0,
//            'runDepth'       => $runDepth,
//            'runOnAllLeads'  => true,
//        ];
//
//        if ($testing) {
//            // TODO: Write tests for this -- needs a lot of data in place, and there's basically no documentation about how that data has to be structured
//            // TODO: add documentation about the automation engine (NOTE: A lot of the inline comments are inaccurate, or outdated)
//            return $workload;
//        }
//
//        LoadLibrary::mqJobQueue()->addJob($companyID, 'doScheduleWorkflow', $workload);
//    }
//
//    /**
//     * Create a new opportunity
//     *
//     * @param $event array contains information from automationEventQueue: taskID, triggerData, workflowEventData
//     *
//     * @throws Exception
//     * @see AutomationWorkflowRunner::processWorkflows() for more information about the event variable
//     *
//     */
//    public function runCreateOpportunity($event)
//    {
//        if (!isset($event['workflowEventData']['opportunityAmount'])
//            || !isset($event['workflowEventData']['opportunityDealStageID'])
//            || !isset($event['workflowEventData']['opportunityFallbackOwnerID'])
//            || !isset($event['workflowEventData']['opportunityProbability'])
//            || !isset($event['workflowEventData']['opportunitySecondsToClose'])
//            || !isset($event['workflowEventData']['opportunityStatus'])
//            || !isset($event['workflowEventData']['workflowID'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || !isset($event['triggerData']['whoID'])
//        ) {
//            Log::logTrace(
//                'missing required data for creating opp via automation',
//                Log::TYPE_ERROR,
//                'automation',
//                ['event' => $event]
//            );
//            throw new AutomationException('required arguments missing for runCreateOpportunity');
//        }
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $leadID = $event['triggerData']['whoID'];
//
//        // We need the company's tz for the timestampModifier
//        $company = $this->companyProfileModel->get($companyID);
//        $companyTz = new DateTimeZone($company['salesOfficialTimezone']);
//        $now = new DateTime('now', $companyTz);
//        $timestampModifier = $now->format('M. d, Y g:iA');
//
//        // Gather information for the account and update the lead to be part of the account
//        $leadModel = LoadModel::lead();
//        $lead = $leadModel->get($companyID, $leadID);
//        $ownerID = $lead['ownerID'] ?? $event['workflowEventData']['opportunityFallbackOwnerID'] ?? null;
//        $accountID = $event['workflowEventData']['accountID'] ?? $lead['accountID'] ?? 0;
//        $leadChanges = [];
//        if (!$accountID) {
//            if (!empty($lead['companyName'])) {
//                $account = $this->accountModel->getByName($companyID, $lead['companyName']);
//                if ($account) {
//                    $accountID = $account['id'];
//                }
//            }
//
//            if (!$accountID) {
//                $accountNamePrefix = (($event['workflowEventData']['accountSubOption'] != 'autoGenerate' &&
//                    $event['workflowEventData']['accountNamePrefix']) ?
//                    $event['workflowEventData']['accountNamePrefix'] : null)
//                    ?:
//                    $lead['companyName'] ?: 'Auto-generated';
//
//                $accountName = "$accountNamePrefix - $timestampModifier";
//                ['id' => $accountID] = $this->accountModel->atomicUpsert(
//                    $companyID,
//                    [
//                        'accountName' => $accountName,
//                        'ownerID'     => $ownerID,
//                    ]
//                );
//                if (!$accountID) {
//                    throw new RetryableAutomationException('failed to create account');
//                }
//            }
//
//            $leadChanges['accountID'] = $accountID;
//            if (!$lead['ownerID']) {
//                $leadChanges['ownerID'] = $ownerID;
//            }
//        }
//
//        // Note: column `opportunity.visualWorkflowID` is nullable
//        $visualWorkflowID = null;
//
//        // Gather data for the opportunity and create it
//        if (!empty($event['taskID'])) {
//            $task = LoadModel::automationTask()->get($companyID, $event['taskID']);
//            if (!empty($task)) {
//                $visualWorkflowID = $task['visualWorkflowID'];
//            }
//        } elseif (!empty($event['workflowID'])) {
//            $visualWorkflow = VisualWorkflow::getByPrimaryActionGroupID($companyID, $event['workflowID']);
//            if (!empty($visualWorkflow)) {
//                $visualWorkflowID = $visualWorkflow['id'];
//            }
//        }
//
//        $workflow = $this->workflowModel->get($companyID, $event['workflowID']);
//        $secondsToClose = $event['workflowEventData']['opportunitySecondsToClose'];
//        $closeDate = date('Y-m-d H:i:s', time() + $secondsToClose);
//        $dealStageID = $event['workflowEventData']['opportunityDealStageID'];
//        $amount = $event['workflowEventData']['opportunityAmount'] ?: 0;
//        $probability = $event['workflowEventData']['opportunityProbability'] ?: 1;
//        $opportunityNamePrefix = $lead['companyName'] ?: ($workflow['workflowName'] ?? 'un-named');
//        $name = "$opportunityNamePrefix - $timestampModifier";
//        $campaignID = $lead['campaignID'] ?? null;
//
//        $data = array_merge(
//            [
//                'accountID'           => $accountID,
//                'amount'              => $amount,
//                'closeDate'           => $closeDate,
//                'dealStageID'         => $dealStageID,
//                'originatingLeadID'   => $leadID,
//                'ownerID'             => $ownerID,
//                'primaryLeadID'       => $leadID,
//                'probability'         => $probability,
//                'needsAcknowledgment' => 1,
//                'visualWorkflowID'    => $visualWorkflowID,
//                'campaignID'          => $campaignID,
//            ],
//            Opportunity::getStatusFromEnumValue($event['workflowEventData']['opportunityStatus'])
//        );
//
//        $leadIsCustomer = false;
//        $leadHasOpp = empty($data['isClosed']);
//        if (!$leadHasOpp) {
//            $now = new DateTime('now', $companyTz);
//            $data['closeDate'] = $now->format('Y-m-d H:i:s');
//            $dealStage = LoadModel::dealStage()->get($companyID, $dealStageID);
//
//            if (!empty($data['isWon']) && (empty($dealStage['pipelineType']) || $dealStage['pipelineType'] === 'sales')) {
//                $leadIsCustomer = true;
//            }
//        }
//
//        $id = $this->opportunityModel->insert($companyID, $accountID, $dealStageID, $name, $data, [$leadID]);
//        if (!$id) {
//            Log::log(
//                'automation event failed to create new opp',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runCreateOpportunity',
//                [
//                    'result'      => $id,
//                    'companyID'   => $companyID,
//                    'accountID'   => $accountID,
//                    'dealStageID' => $dealStageID,
//                    'name'        => $name,
//                    'data'        => $data,
//                    'leadID'      => $leadID,
//                ]
//            );
//            throw new RetryableAutomationException('failed to create opp');
//        }
//        if ($leadIsCustomer) {
//            LoadModel::lead()->setLeadIsCustomer($companyID, $leadID);
//        }
//
//        $leadChanges['hasOpportunity'] = $leadHasOpp ? '1' : '0';
//        $leadChanges['isContact'] = '1';
//        $updated = LoadModel::lead()->update($companyID, $leadChanges, ['id' => $leadID]);
//        if (!$updated) {
//            throw new RetryableAutomationException('failed to update lead account');
//        }
//        if (!empty($leadChanges['accountID'])) {
//            // check autonotify settings for the lead
//            $leadModel->setNotificationPreferences($companyID, $ownerID, [$leadID], UserSettings::AUTONTFY_OWNED_ACCOUNTS);
//        }
//
//        // check autonotify settings for the lead
//        $leadModel->setNotificationPreferences($companyID, $ownerID, [$leadID], UserSettings::AUTONTFY_OWNED_OPPS_PRIMARY);
//        $this->opportunityModel->createNewOpportunityEvent($companyID, $id, $leadID);
//    }
//
//    /**
//     * Create a task
//     *
//     * @param $event
//     *
//     * @throws AutomationException
//     * @throws RetryableAutomationException
//     * @throws Exception
//     */
//    public function runCreateTask($event)
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['user']['id'])
//            || !isset($event['workflowEventData']['taskType'])
//            || !isset($event['workflowEventData']['title'])
//            || !isset($event['workflowEventData']['dueSameDay'])
//            || (!empty($event['workflowEventData']['dueSameDay'])
//                && (!isset($event['workflowEventData']['dueInHours']) || !isset($event['workflowEventData']['dueInMinutes'])))
//            || (empty($event['workflowEventData']['dueSameDay']) && !isset($event['workflowEventData']['dueInDays']))
//            || !isset($event['workflowEventData']['authorID'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['whoID'])
//            || !isset($event['triggerData']['whoType'])
//            || $event['triggerData']['whoType'] !== 'lead'
//            || !isset($event['workflowID'])
//        ) {
//            Log::log(
//                'automation event failed to create new user task',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runCreateTask',
//                [
//                    'identifier' => 'eventRunner::runCreateTask',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $automationTaskSvc = new AutomationUserTask();
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $userID = $event['workflowEventData']['user']['id'];
//        $authorID = $event['workflowEventData']['authorID'];
//        $workflowID = $event['workflowID'];
//
//        // Connect to the triggering lead
//        if (isset($event['triggerData']['whoID']) && $event['triggerData']['whoType'] === 'lead') {
//            $whoID = $event['triggerData']['whoID'];
//            $whoType = 'lead';
//        }
//
//        // Connect to the triggering opp if there is one
//        if (isset($event['triggerData']['whatID']) && $event['triggerData']['whatType'] === 'opportunity') {
//            $whatID = $event['triggerData']['whatID'];
//            $whatType = 'opp';
//        } elseif ($event['whatType'] === 'opportunity' && !empty($event['whatID'])) {
//            // This is the opp that succeeded all the automation filters for opp workflows
//            $whatID = $event['whatID'];
//            $whatType = 'opp';
//        } else {
//            $whatID = null;
//            $whatType = null;
//        }
//
//        // Assign to lead owner instead
//        if (!empty($event['workflowEventData']['assignToLeadOwner']) && $whoID) {
//            $lead = $this->leadModel->get($companyID, $whoID);
//            if (!empty($lead['ownerID'])) {
//                $userID = $lead['ownerID'];
//            }
//        }
//
//        // When is this task due?
//        $dueSameDay = !empty($event['workflowEventData']['dueSameDay']); // Is this task due the same day or not
//        $dueInHours = (int)$event['workflowEventData']['dueInHours']; // if same day, how many hours after trigger is task due?
//        $dueInMinutes = (int)$event['workflowEventData']['dueInMinutes']; // if same day, how many minutes after trigger is task due?
//        $dueInDays = (int)$event['workflowEventData']['dueInDays']; // if not same day, how many days after trigger is task due?
//        $dueTime = $event['workflowEventData']['dueTime'] ?? null; // optional, if not same day, make it due at $dueTime time on the day, Format '07:30'
//        $duringBusinessHrs = $event['workflowEventData']['dueDuringBusinessHours'] ?? false; // optional
//        $dueTimeSplit = !empty($dueTime) ? explode(':', $dueTime) : [];
//
//        // We're gonna need company business hours and timezone
//        $company = $this->companyProfileModel->get($companyID);
//        $startHour = isset($company['salesStartHour']) ? (int)$company['salesStartHour'] : 8;
//        $endHour = isset($company['salesEndHour']) ? (int)$company['salesEndHour'] : 17;
//        $businessDaysBitmask = $company['salesBusinessDaysBitmask'];
//        $companyTz = new DateTimeZone($company['salesOfficialTimezone']);
//        $utcTz = new DateTimeZone('UTC');
//
//        $taskDueDate = new DateTime('now', $utcTz); // Make sure it's in UTC for now
//
//        if ($dueSameDay) {
//            $taskDueDate->add(DateInterval::createFromDateString("$dueInHours hours $dueInMinutes minutes"));
//        } else {
//            $taskDueDate->add(DateInterval::createFromDateString("$dueInDays days"));
//            if (!empty($dueTime) && count($dueTimeSplit) === 2) {
//                // If time was specified, use it
//                $dueHour = (int)$dueTimeSplit[0];
//                $dueMinute = (int)$dueTimeSplit[1];
//                // First set to company timezone, as the hour and minute should be in company's local time
//                $taskDueDate->setTimezone($companyTz);
//                // set the time
//                $taskDueDate->setTime($dueHour, $dueMinute);
//                // and convert back to UTC
//                $taskDueDate->setTimezone($utcTz);
//            } else {
//                // Otherwise it's due end of business day
//                // First set the timezone to the company's
//                $taskDueDate->setTimezone($companyTz);
//                // Set the hour to the end of the business day
//                $taskDueDate->setTime($endHour, 0);
//                // Convert back to UTC
//                $taskDueDate->setTimezone($utcTz);
//            }
//        }
//
//        if (!empty($duringBusinessHrs)) {
//            // Calculating the next in-business-hrs time is generously cribbed off of automationworkflowrunner.php->getBusinessStartTime()
//            // with a few tweaks - namely that being due at the exact end of the business day is a-okay
//
//            // First convert to the company's timezone
//            $taskDueDate->setTimezone($companyTz);
//
//            $currentHour = (int)$taskDueDate->format('H');
//            $currentMinute = (int)$taskDueDate->format('i');
//            $currentDay = (int)$taskDueDate->format('w');
//
//            $dayOffset = 0;
//
//            // Set the appropriate business hour
//            if ($currentHour < $startHour) {
//                $currentHour = $startHour;
//                $currentMinute = 0;
//            } elseif ($currentHour > $endHour || ($currentHour == $endHour && $currentMinute > 0)) {
//                $currentHour = $startHour + $dueInHours;
//                $currentMinute = $dueInMinutes;
//                $currentDay = ($currentDay + 1) % 7;
//                $dayOffset = 1;
//            }
//
//            // Set the appropriate business day. Ignore this if there are no days marked as business days.
//            if (!($businessDaysBitmask >> (6 - $currentDay) & 1) && $businessDaysBitmask !== 0) {
//                // We need to start by adding 1, and we don't have to wrap around to add 7.
//                for ($i = 1; $i < 7; $i++) {
//                    $shift = 6 - (($currentDay + $i) % 7);
//                    if ($businessDaysBitmask >> $shift & 1) {
//                        $dayOffset += $i;
//                        // Unlike automationworkflowrunner.php, we don't want to change the hour or minute, just the day
//                        break;
//                    }
//                }
//            }
//
//            // If we bumped it to another day, and it was originally due the same day,
//            // we want to set it to the start of the business day
//            if (!empty($dayOffset) && $dueSameDay) {
//                // Set the hour to the start of the business day
//                $taskDueDate->setTime($startHour, 0);
//                $currentHour = (int)$taskDueDate->format('H');
//                $currentMinute = (int)$taskDueDate->format('i');
//            }
//
//            // If the next available business day is next week, we add a week to the date.
//            // We add the day offset regardless. If the current time is within business hours, it will simply add 0 days.
//            $dayOffset = ($dayOffset >= 0 ? '+' : '+1 week ') . $dayOffset . ' days';
//            $newTime = strtotime(
//                $taskDueDate->format('Y-m-d ' . $currentHour . ':' . $currentMinute . ':s') .
//                ' ' .
//                $company['salesOfficialTimezone'] .
//                ' ' .
//                $dayOffset
//            );
//
//            // now set the correct time and convert back to UTC
//            $taskDueDate->setTimestamp($newTime);
//            $taskDueDate->setTimezone($utcTz);
//        }
//
//        //  Process merge variables in title and note
//        $mergeTitle = $event['workflowEventData']['title'];
//        $mergeNote = $event['workflowEventData']['note'] ?? null;
//
//        if (!empty($whoID)) {
//            if (!empty($mergeTitle)) {
//                $mergeVars = $this->extractMergeVariables((int)$companyID, (int)$whoID, $mergeTitle);
//                if (!empty($mergeVars)) {
//                    $mergeTitle = $this->replaceMergeVariables($mergeTitle, $mergeVars);
//                }
//            }
//            if (!empty($mergeNote)) {
//                $mergeVars = $this->extractMergeVariables((int)$companyID, (int)$whoID, $mergeNote);
//                if (!empty($mergeVars)) {
//                    $mergeNote = $this->replaceMergeVariables($mergeNote, $mergeVars);
//                }
//            }
//        }
//
//        // create the task (API will handle potential reassignment)
//        $newTaskData = $automationTaskSvc->createTask(
//            $companyID,
//            $event['workflowEventData']['taskType'],
//            $taskDueDate->getTimestamp(),
//            $whatID,
//            $whatType,
//            $whoID ?? null,
//            $whoType ?? null,
//            $mergeTitle,
//            $userID,
//            $authorID,
//            null, // parentID
//            false, // allDayTask
//            true, // isAutomated
//            $workflowID,
//            $mergeNote
//        );
//
//        $taskID = $newTaskData['id'];
//        $ownerID = $newTaskData['ownerID'];
//
//        if (empty($taskID)) {
//            throw new RetryableAutomationException('runCreateTask failed to create task');
//        }
//
//        $userTaskSvc = new UserTask();
//        if (!empty($event['workflowEventData']['emailResources'])) {
//            $personalization = $event['workflowEventData']['smartMailPersonalization'] ?? false;
//            foreach ($event['workflowEventData']['emailResources'] as $emailResource) {
//                $userTaskSvc->addUserTaskEmail($companyID, $taskID, $emailResource['id'], $personalization);
//            }
//        }
//
//        if (!empty($event['workflowEventData']['mediaResources'])) {
//            foreach ($event['workflowEventData']['mediaResources'] as $mediaResource) {
//                $userTaskSvc->addUserTaskMediaLink($companyID, $taskID, $mediaResource['id']);
//            }
//        }
//
//        // Schedule iCal invite
//        if (!empty($event['workflowEventData']['sendCalInvite'])) {
//            $user = $this->userProfileModel->get($companyID, $ownerID);
//            $userLang = $user['locale'];
//            $userLang = $userLang == 'english' || empty($userLang) ? 'en_US' : $userLang;
//            $userTz = $user['userTimezone'] ?? null;
//
//            if (empty($userTz)) {
//                // fallback to company timezone
//                $userTz = $company['salesOfficialTimezone'] ?? null;
//            }
//
//            if (!empty($whoID)) {
//                $lead = $this->leadModel->get($companyID, $whoID);
//                $leadData = [
//                    'url'         => $this->companyProfileModel->getBaseURL($companyID) . "/lead/$whoID",
//                    'phone'       => $lead['phoneNumber'] ?? '',
//                    'email'       => $lead['emailAddress'] ?? '',
//                    'companyName' => $lead['companyName'] ?? '',
//                    'contactName' => $lead['firstName'] ?? '' . ' ' . $lead['lastName'] ?? '',
//                ];
//            } else {
//                $leadData = null;
//            }
//
//            $this->iCal->scheduleInvite(
//                $companyID,
//                $user['emailAddress'],
//                $taskID,
//                0,
//                $userLang,
//                $leadData,
//                null,
//                $userTz
//            );
//        }
//    }
//
//    /**
//     * Change the opportunity stage for an opportunity (part of a opportunity workflow)
//     *
//     * @param $event
//     *
//     * @throws AutomationException
//     */
//    public function runChangeOpportunityStage($event)
//    {
//        if (!isset($event['workflowEventData'])
//            || !isset($event['workflowEventData']['pipeline'])
//            || !isset($event['workflowEventData']['opportunityDealStageID'])
//            || !isset($event['workflowID'])
//            || !isset($event['triggerData'])
//            || !isset($event['triggerData']['companyProfileID'])
//            || !isset($event['triggerData']['whoID'])
//            || !isset($event['triggerData']['whoType'])
//            || $event['triggerData']['whoType'] !== 'lead'
//        ) {
//            Log::log(
//                'automation event failed to change opp stage',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runChangeOpportunityStage',
//                [
//                    'identifier' => 'eventRunner::runChangeOpportunityStage',
//                    'message'    => 'function call failed to parse',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $companyID = $event['triggerData']['companyProfileID'];
//        $pipelineID = $event['workflowEventData']['pipeline'];
//        $dealStageID = $event['workflowEventData']['opportunityDealStageID'];
//        $triggerOppID = !empty($event['triggerData']['whatType']) && $event['triggerData']['whatType'] === 'opportunity'
//            ?
//            $event['triggerData']['whatID']
//            :
//            null;
//        $workflowOppID = $event['whatType'] === 'opportunity' && !empty($event['whatID']) ? $event['whatID'] : null;
//
//        // First we have to find the opp to apply the action to
//        if (!empty($triggerOppID)) {
//            // if a specific opp triggered this workflow, use it
//            $opp = $this->opportunityModel->get($companyID, $triggerOppID);
//            $allOpps = [$opp];
//        } elseif (!empty($workflowOppID)) {
//            // This is the opp that succeeded all the automation filters for opp workflows
//            $opp = $this->opportunityModel->get($companyID, $workflowOppID);
//            $allOpps = [$opp];
//        }
//
//        // Filter opps to ones that are in the given pipeline
//        $allOpps = array_filter($allOpps, function($opp) use ($pipelineID) {
//            return $opp['pipelineID'] == $pipelineID;
//        });
//
//        // If the lead has no opps in the pipeline, we're done
//        if (count($allOpps) !== 1) {
//            return;
//        }
//
//        $opp = reset($allOpps);
//
//        if ($opp['dealStageID'] == $dealStageID) {
//            // It's already in this dealstage
//            return;
//        }
//        $runDepth = $event['workflowEventData']['runDepth'] ?? 0;
//        $this->opportunityModel->setDealStage(
//            $companyID,
//            $opp['id'],
//            $opp['probability'],
//            $dealStageID,
//            $opp['dealStageID'],
//            $runDepth + 1,
//            $event['workflowID']
//        );
//    }
//
//    /**
//     * Add a record to the conversionGoalHistory table.
//     *
//     * @param array $event
//     *
//     * @throws AutomationException - When required args are not passed.
//     * @throws Exception
//     */
//    public function runConversionGoalMet(array $event)
//    {
//        [
//            'companyProfileID' => $companyID,
//            'taskID'           => $taskID,
//            'triggerData'      => $triggerData,
//        ] = $event;
//        if (!isset($companyID)
//            || !isset($taskID)
//            || !isset($triggerData)
//            || !isset($triggerData['whoID'])
//            || !isset($triggerData['whoType'])
//        ) {
//            Log::log(
//                'Automation event failed to create a met conversion goal record.  Essential parameters missing.',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner->runConversionGoalMet',
//                [
//                    'identifier' => 'eventRunner::runConversionGoalMet',
//                    'message'    => 'Function call failed to parse.  Essential parameters missing.',
//                    'details'    => $event,
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $conversionGoalSvc = new ConversionGoalService();
//        $goal = $conversionGoalSvc->getByTaskID($companyID, $taskID);
//        $goalType = (int)$goal['goalType'];
//
//        $dealStageID = $triggerData['dealStageID'] ?? null;
//        $eventSource = $triggerData['eventSource'];
//        $formerDealStageID = $triggerData['formerDealStageID'] ?? null;
//        $whoID = $triggerData['whoID'] ?? null;
//        $whoType = $triggerData['whoType'] ?? null;
//
//        // Goal type-dependent input validation.
//        if (($goalType === ConversionGoalType::LEAD_FIELD && $whoType !== 'lead')
//            || ($goalType === ConversionGoalType::OPP_FIELD && $whoType !== 'opportunity')
//            || ($goalType === ConversionGoalType::ACCOUNT_FIELD && $whoType !== 'account')
//            || ($goalType === ConversionGoalType::FORM_FILL && $whoType !== 'lead')
//            || (($goalType === ConversionGoalType::PROJ_PIPELINE_STAGE
//                    || $goalType === ConversionGoalType::PROJ_PIPELINE_STAGE) && $whoType !== 'opportunity'
//                && (!isset($dealStageID) || !isset($formerDealStageID)))
//        ) {
//            Log::error(
//                'Automation event failed to create a met conversion goal record.  Required goal type-dependent data missing.',
//                'AutomationEventRunner->runConversionGoalMet',
//                [
//                    'identifier' => 'eventRunner::runConversionGoalMet',
//                    'message'    => 'Function call failed to parse.  Required goal type-dependent data missing.',
//                    'goalType'   => $goalType,
//                    'details'    => $event,
//                ]
//            );
//            throw new AutomationException();
//        }
//
//        $automationRuleSvc = new AutomationRule();
//        $rules = $automationRuleSvc->getAllByTaskID($companyID, $taskID);
//        if (empty($rules)) {
//            Log::log(
//                'No rule found for task ID',
//                Log::TYPE_ERROR,
//                'AutomationEventRunner::runConversionGoalMet',
//                [
//                    'identifier' => 'eventRunner::runConversionGoalMet',
//                    'message'    => 'no rule found for task ID',
//                    'details'    => json_encode($event),
//                ]
//            );
//            throw new AutomationException();
//        }
//        $whatID = $rules[0]->compareItemID;
//        $prevValuesMap = $triggerData['prevValuesMap'] ?? [];
//
//        switch ($goalType) {
//            case ConversionGoalType::LEAD_FIELD:
//                $whatType = 'contactField';
//
//                // Check for the owner ID field case.  If only field of concern is the owner ID, use that as the 'what'.
//                $whatID = $this->screenWhatIDForOwnerIDChange($companyID, $prevValuesMap, $whatID, $eventSource);
//
//                $targetField = $this->field->getByIDs($companyID, [$whatID])[0];
//                $whatName = $targetField['label'];
//
//                // Get current field value.
//                $values = $this->leadFieldValue->getValuesForLeads($companyID, null, [$whoID], null, null, true, '%b %e, %Y %l:%M:%S %p')[$whoID];
//                $newValue = $values[$whatID];
//
//                // Get previous value from trigger data.
//                $prevValue = $prevValuesMap[$whatID] ?? null;
//                break;
//            case ConversionGoalType::OPP_FIELD:
//                $whatType = 'oppField';
//                $targetField = $this->field->getByIDs($companyID, [$whatID])[0];
//                $whatName = $targetField['label'];
//
//                // Get all current (system and custom) fields.
//                $values = $this->opportunityFieldValueModel->getValuesForOpportunity($companyID, $whoID, $whatID, true);
//                $newValue = $values[$whatID]['value'];
//
//                // Get previous value from trigger data.
//                $prevValue = $prevValuesMap[$whatID] ?? null;
//
//                // Associate leads for secondary history records.
//                $associatedLeadIDs = $this->opportunityModel->getLeadIDs($companyID, $whoID);
//
//                break;
//            case ConversionGoalType::ACCOUNT_FIELD:
//                $whatType = 'accountField';
//
//                // Check for the owner ID field case.  If only field of concern is the owner ID, use that as the 'what'.
//                $whatID = $this->screenWhatIDForOwnerIDChange($companyID, $prevValuesMap, $whatID, $eventSource);
//
//                $targetField = $this->field->getByIDs($companyID, [$whatID])[0];
//                $whatName = $targetField['label'];
//
//                $currRecordValuesMap = LoadModel::accountFieldValue()->fetchCurrentRecordValuesMap($companyID, $whoID);
//                $newValue = $currRecordValuesMap[$whatID]['value'];
//
//                // Get previous value from trigger data.
//                $prevValue = $prevValuesMap[$whatID] ?? null;
//
//                // Associate leads for secondary history records.
//                $associatedLeads = $this->accountModel->getRelatedLeads($companyID, $whoID);
//                $associatedLeadIDs = reValueSingle($associatedLeads, 'id');
//                break;
//            case ConversionGoalType::FORM_FILL:
//                $whatType = 'formSubmission';
//                if (count($rules) > 1) {
//                    $result = $this->formEventHistory->getMostRecentFormSubmissionForLead($companyID, $whoID);
//                    if (isset($result)) {
//                        $whatID = $result['formGUID'];
//                    }
//                }
//
//                $form = LoadModel::companyForm()->getByUUID($whatID);
//                $whatID = $form['id'];
//                $whatName = $form['formName'];
//                break;
//            case ConversionGoalType::PIPELINE_STAGE:
//            case ConversionGoalType::PROJ_PIPELINE_STAGE:
//                $whatType = 'pipeline';
//                if ($goalType === ConversionGoalType::PROJ_PIPELINE_STAGE) {
//                    $whatType = 'projectPipeline';
//                }
//
//                $dealStage = LoadModel::dealStage();
//                $prevValue = $dealStage->get($companyID, $formerDealStageID);
//                $newValue = $dealStage->get($companyID, $dealStageID);
//
//                $prevValue = $prevValue['dealStageName'] ?? null;
//                [
//                    'pipelineName'  => $whatName,
//                    'pipelineID'    => $whatID,
//                    'dealStageName' => $newValue,
//                ] = $newValue;
//                if ($whatID == 0) {
//                    $whatName = 'Sales Pipeline';
//                }
//
//                // Associate leads for secondary history records.
//                $associatedLeadIDs = $this->opportunityModel->getLeadIDs($companyID, $whoID);
//        }
//
//        $prevValue = $prevValue ?? null;
//        $newValue = $newValue ?? null;
//        if ($goalType !== ConversionGoalType::FORM_FILL && $newValue == $prevValue) {
//            return;
//        }
//
//        $task = LoadModel::automationTask()->get($companyID, $taskID);
//
//        $convGoalHistory = new ConversionGoalHistory();
//        $convGoalHistory->companyProfileID = $companyID;
//        $convGoalHistory->whoType = $whoType;
//        $convGoalHistory->whoID = $whoID;
//        $convGoalHistory->whatType = $whatType;
//        $convGoalHistory->whatID = $whatID;
//        $convGoalHistory->whatName = $whatName;
//        $convGoalHistory->conversionGoalID = $goal['id'];
//        $convGoalHistory->goalTitle = $task['taskName'];
//        $convGoalHistory->prevValue = $prevValue;
//        $convGoalHistory->newValue = $newValue;
//
//        $convGoalHistorySvc = new ConversionGoalHistoryService();
//        $convGoalHistorySvc->createConversionGoalHistory($convGoalHistory);
//
//        // Create secondary conversion goal history records for non-lead related goals being met.
//        if (isset($associatedLeadIDs)) {
//            $this->createSecondaryLeadConvGoalHistoryRecords(
//                $convGoalHistory,
//                $associatedLeadIDs,
//                $convGoalHistorySvc
//            );
//        }
//    }
//
//    /**
//     * Non-lead based conversion goals being met primarily produce a conversion goal for themselves,
//     * but also require secondary conversion goals for associated leads. Create the latter in a batch.
//     *
//     * @param ConversionGoalHistory        $primaryEvent
//     * @param int[]                        $leadIDs
//     * @param ConversionGoalHistoryService $convGoalHistorySvc
//     */
//    private function createSecondaryLeadConvGoalHistoryRecords(
//        ConversionGoalHistory $primaryEvent,
//        array $leadIDs,
//        ConversionGoalHistoryService $convGoalHistorySvc
//    ) {
//        $rows = [];
//        foreach ($leadIDs as $id) {
//            // Clone the primary event and write the lead info.
//            $secondaryEvent = clone $primaryEvent;
//            $secondaryEvent->whoType = 'lead';
//            $secondaryEvent->whoID = $id;
//            array_push($rows, $secondaryEvent);
//        }
//
//        // Create all the lead conversion goals.
//        $convGoalHistorySvc->createConversionGoalHistories($rows);
//    }
//
//    /**
//     * When checking for whatID for field change conversion goals, owner ID field changes automation rules are written
//     * and processed differently (due to differing automation event types).  Determine which ID is most appropriate.
//     *
//     * @param int    $companyID
//     * @param array  $prevValuesMap
//     * @param int    $whatID
//     * @param string $eventSource
//     *
//     * @return int - Correct whatID for the circumstance.
//     */
//    private function screenWhatIDForOwnerIDChange(int $companyID, array $prevValuesMap, int $whatID, string $eventSource)
//    {
//        if ($eventSource === 'leadOwnerChange' || $eventSource === 'accountOwnerChange') {
//            $idUnderExamination = array_key_first($prevValuesMap);
//            $fieldUnderExamination = $this->field->getByIDs($companyID, [$idUnderExamination])[0];
//
//            // If only field of concern in the owner ID, use that as the 'what'.
//            if ($fieldUnderExamination['systemName'] !== 'ownerID') {
//                // TODO: If this scenario never happens, consider removing this check.
//                Log::maybeDead();
//                Log::log(
//                    'A conversion goal being met triggered by a lead/account owner change provided more previous values than expected (expected exactly 1).',
//                    Log::TYPE_ERROR,
//                    'AutomationEventRunner->screenWhatIDForOwnerIDChange',
//                    [
//                        'identifier'    => 'eventRunner::screenWhatIDForOwnerIDChange',
//                        'message'       => 'Expectation violated while trying to meet a conversion goal.',
//                        'eventSource'   => $eventSource,
//                        'initialWhatID' => $whatID,
//                        'prevValuesMap' => $prevValuesMap,
//                    ]
//                );
//                throw new AutomationException();
//            }
//
//            return $idUnderExamination;
//        }
//
//        return $whatID;
//    }
//
//    /**
//     * Add a tag to a lead
//     *
//     * @param array $event
//     */
//    public function runAddTagToLead($event)
//    {
//        static $logSection = 'AutomationEventRunner::runAddTagToLead';
//        static $logIdentifier = 'eventRunner::addTagToLead';
//
//        $eventData = $event['workflowEventData'] ?? [];
//        $triggerData = $event['triggerData'] ?? [];
//        $companyID = $triggerData['companyProfileID'] ?? null;
//        $leadID = $triggerData['whoID'] ?? null;
//        $tagID = $eventData['tagID'] ?? [];
//        if (empty($companyID) || empty($leadID) || empty($tagID)) {
//            Log::log(
//                'automation event cannot add tag to lead',
//                Log::TYPE_ERROR,
//                $logSection,
//                [
//                    'identifier' => $logIdentifier,
//                    'message'    => 'Missing required parameter',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//
//        //  Assign tag
//        LoadModel::tag()->assignTagToLeadID($companyID, $tagID, $leadID);
//
//        Log::debug(
//            'added tag to lead',
//            $logSection,
//            [
//                'tagID'     => $tagID,
//                'leadID'    => $leadID,
//                'companyID' => $companyID,
//            ]
//        );
//    }
//
//    /**
//     * Remove a tag from a lead
//     *
//     * @param array $event
//     */
//    public function runRemoveTagFromLead($event)
//    {
//        static $logSection = 'AutomationEventRunner::runRemoveTagFromLead';
//        static $logIdentifier = 'eventRunner::removeTagFromLead';
//
//        $eventData = $event['workflowEventData'] ?? [];
//        $triggerData = $event['triggerData'] ?? [];
//        $companyID = $triggerData['companyProfileID'] ?? null;
//        $leadID = $triggerData['whoID'] ?? null;
//        $tagID = $eventData['tagID'] ?? [];
//        if (empty($companyID) || empty($leadID) || empty($tagID)) {
//            Log::log(
//                'automation event cannot remove tag from lead',
//                Log::TYPE_ERROR,
//                $logSection,
//                [
//                    'identifier' => $logIdentifier,
//                    'message'    => 'Missing required parameter',
//                    'details'    => json_encode($event),
//                ]
//            );
//        }
//
//        LoadModel::tag()->removeTag($companyID, Tag_model::LEAD_TYPE, $leadID, $tagID);
//
//        Log::debug(
//            'removed tag from lead',
//            $logSection,
//            [
//                'tagID'     => $tagID,
//                'leadID'    => $leadID,
//                'companyID' => $companyID,
//            ]
//        );
//    }
//
//    /**
//     * @param array $event
//     *
//     * @return array
//     */
//    public function runTest($event)
//    {
//        $newData = [];
//        foreach ($event['triggerData'] as $k => $v) {
//            $newData[$k] = strtoupper($v);
//        }
//
//        foreach ($event['workflowEventData'] as $k => $v) {
//            $newData[$k] = strrev($v);
//        }
//
//        return $newData;
//    }
//
//    /**
//     * Loads fields for merge variables
//     *
//     * @param int $companyID
//     */
//    protected function loadMergeVariableFields(int $companyID): void
//    {
//        if ($this->mvFields !== null && $this->mvCompanyID === $companyID) {
//            return;
//        }
//
//        $this->mvFields = LoadModel::field()->getAllMergeVariableFields($companyID);
//        // Replace multiple whitespaces in labels with single whitespace
//        foreach ($this->mvFields as $key => $field) {
//            if (!empty($field['label'])) {
//                $this->mvFields[$key]['label'] = preg_replace('/\s\s+/', ' ', $field['label']);
//            }
//        }
//
//        // map special legacy lead fields to new fields
//        $this->mvFieldsByLabel = arrayToDict($this->mvFields, 'label');
//        $this->mvFieldsByLabel['firstname'] = $this->mvFieldsByLabel['First Name'];
//        $this->mvFieldsByLabel['emailaddress'] = $this->mvFieldsByLabel['Email'];
//        $this->mvFieldsByLabel['lastname'] = $this->mvFieldsByLabel['Last Name'];
//        $this->mvFieldsByLabel['companyname'] = $this->mvFieldsByLabel['Company Name'];
//
//        $this->mvFieldsBySystemName = arrayToDict($this->mvFields, 'systemName');
//
//        $this->mvGlobalVariables = arrayToDict(LoadModel::emailVariable()->getAll($companyID), 'systemName');
//
//        $this->mvCompanyID = $companyID;
//    }
//
//    /**
//     * Extract any merge variables from a string
//     *
//     * @param int    $companyID
//     * @param int    $leadID
//     * @param string $toProcess
//     *
//     * @return array
//     */
//    protected function extractMergeVariables(int $companyID, int $leadID, string $toProcess): array
//    {
//        $this->loadMergeVariableFields($companyID);
//
//        $content = html_entity_decode($toProcess, ENT_QUOTES);
//
//        // keep track of variables with default values
//        $defaultValueVariablesByLabel = [];
//        $matches = $mergeVariables = [];
//
//        $localNameRegex = '/{\$([^!}]+)(!"([^"]*)")*}/';
//        preg_match_all($localNameRegex, $content, $matches);
//        $fieldLabels = $matches[1];
//        $defaultValues = $matches[3];
//        for ($i = 0; $i < count($fieldLabels); $i++) {
//            $label = $fieldLabels[$i];
//            $defaultValue = $defaultValues[$i];
//
//            // skip dynamic layout background merge vars
//            if (substr($label, 0, 17) === 'dynamicBackground') {
//                continue;
//            }
//
//            // keep track of any variables with default values
//            if ($defaultValue) {
//                $defaultValueVariablesByLabel[$label] = $defaultValue;
//            } elseif (!$defaultValue && isset($defaultValueVariablesByLabel[$label])) {
//                // if this instance of the variable has no default value, just skip it,
//                // if the variable exists somewhere else
//                continue;
//            }
//
//            if (isset($this->mvGlobalVariables[$label])) {
//                $mergeVariable = $this->mvGlobalVariables[$label];
//                $isGlobalVariable = 1;
//            } elseif (isset($this->mvFieldsBySystemName[$label])) {
//                $mergeVariable = $this->mvFieldsBySystemName[$label];
//                $isGlobalVariable = 0;
//            } elseif (isset($this->mvFieldsByLabel[$label])) {
//                $mergeVariable = $this->mvFieldsByLabel[$label];
//                $isGlobalVariable = 0;
//            } else {
//                //  Field not a merge variable...
//                continue;
//            }
//
//            if ($isGlobalVariable) {
//                $value = $this->mvGlobalVariables[$label]['value'] ?? null;
//            } else {
//                $value = $this->leadFieldValue->getValueForLead($companyID, $mergeVariable['id'], $leadID);
//            }
//
//            //  Use the default value if there is one
//            if (empty($value) && !empty($defaultValue)) {
//                $value = $defaultValue;
//            }
//
//            $mergeVariableResult = [
//                'defaultValue' => $defaultValue,
//                'fieldID'      => $mergeVariable['id'],
//                'systemName'   => $mergeVariable['systemName'],
//                'value'        => $value,
//            ];
//
//            // Dedupe mergeVariables as we generate the array
//            $hash = $this->mergeVariableHelper->hashMergeVariable($mergeVariableResult);
//            $mergeVariables[$hash] = $mergeVariableResult;
//        }
//
//        return array_values($mergeVariables);
//    }
//
//    /**
//     * Replace merge variables in string
//     *
//     * @param string $toProcess
//     * @param array  $mergeVariables
//     *
//     * @return string
//     */
//    protected function replaceMergeVariables(string $toProcess, array $mergeVariables): string
//    {
//        $processed = $toProcess;
//        foreach ($mergeVariables as $variable) {
//            if (!empty($variable['systemName'])) {
//                // replace any instance of static merge variables with the variable itself
//                $localNameRegex = '/{\$' . preg_quote($variable['systemName'], '/') . '(!"[^"]*")*}/';
//                $processed = preg_replace($localNameRegex, $variable['value'], $processed);
//            }
//        }
//
//        return trim($processed);
//    }
//
//    /**
//     * Executes a YesNo branch
//     *
//     * @param array $event
//     */
//    public function runYesNoBranch($event)
//    {
//        $logSection = 'AutomationEventRunner::runYesNoBranch';
//        $runTaskID = $event['workflowEventData']['runTaskID'] ?? null;
//        $companyProfileID = $event['companyProfileID'] ?? null;
//        if (!isset($runTaskID) || !isset($companyProfileID)) {
//            Log::error('missing required fields when calling yesNoBranch event', $logSection, ['data' => $event]);
//        }
//
//        $task = LoadModel::automation()->getTask($companyProfileID, $runTaskID);
//
//        $workload = $event['triggerData'];
//
//        // for testing purposes, and until confirmed otherwise YesNo eventType wont block a children workflow
//        unset($task['requiredWorkflowID']);
//
//        // $this->automationTaskRunner->process(['tasks' => [ $runTaskID => $task]], $workload);
//        $result = $this->automationTaskRunner->evalTask($task, $workload);
//
//        // for YesNo branches, trigger will always evaluates as true
//        $result['trigger'] = $result['sets'][0] = true;
//
//        $workflowResults = [$runTaskID => $result];
//
//        LoadLibrary::automationWorkflowRunner()->process(['tasks' => [$task['id'] => $task]], $workflowResults, $workload);
//    }

    /**
     * Looks up and returns whether a list is a system list
     *
     * @param int $companyID
     * @param int $listID
     *
     * @return bool
     */
    protected function isSystemList(int $companyID, int $listID): bool
    {
        $list = ListModel::where(['companyProfileID' => $companyID, 'id' => $listID])->get();

        return ($list && !empty($list['isSystemList']));
    }
}
