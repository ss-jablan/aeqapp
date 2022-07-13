<?php

namespace App\Libraries;

class Offering
{
    //////// IMPORTANT!!!! ////////
    // If you edit basically anything in this file, you must also update them in the backend repo @ backend/services/api/src/helpers/permissions.ts

    // Offering bits. Some of these are mutually exclusive and some aren't.
    /**
     * Standard SharpSpring Offering (a.k.a. "Marketing Automation")
     */
    const PRO = 1;
    /**
     * SharpSpring Mail
     */
    const ESP = 2;
    /**
     * Dedicated Support
     */
    const SUP = 4;
    /**
     * Sales - Not free CRM
     */
    const CRM = 8;
    /**
     * VisitorID
     */
    const VID = 16;
    /**
     * Beta features
     */
    const BETA = 32;
    /**
     * CRM Free
     */
    const CRM_TIER_ZERO = 64;
    /**
     * CRM Pro Trial
     */
    const CRM_TIER_ZERO_INTRO = 128;
    /**
     * CRM Pro
     */
    const CRM_TIER_ONE = 256;
    /**
     * CRM Ultimate
     */
    const CRM_TIER_TWO = 512;

    /**
     * Perfect Audience Only
     */
    const PERFECT_AUDIENCE_ONLY = 1024;
    /**
     * Perfect Audience Direct
     */
    const PERFECT_AUDIENCE_DIRECT = 2048;

    /* @deprecated use FREE_OFFERING_MASK instead */
    const FREE_OFFERINGS = self::CRM_TIER_ZERO | self::CRM_TIER_ZERO_INTRO;

    /**
     * @var array
     */
    public const ALL_OFFERINGS = [
        self::PRO,
        self::ESP,
        self::SUP,
        self::CRM,
        self::VID,
        self::BETA,
        self::CRM_TIER_ZERO,
        self::CRM_TIER_ZERO_INTRO,
        self::CRM_TIER_ONE,
        self::CRM_TIER_TWO,
        self::PERFECT_AUDIENCE_ONLY,
        self::PERFECT_AUDIENCE_DIRECT,
    ];

    const FREE_OFFERING_MASK = self::CRM_TIER_ZERO | self::CRM_TIER_ZERO_INTRO;
    const CRM_OFFERING_MASK  = self::CRM_TIER_ZERO_INTRO | self::CRM_TIER_ZERO | self::CRM_TIER_ONE | self::CRM_TIER_TWO;

    public const PRIMARY_OFFERING_MASK = self::PRO
    | self::ESP
    | self::CRM_TIER_ONE
    | self::CRM_TIER_TWO
    | self::CRM_TIER_ZERO
    | self::CRM_TIER_ZERO_INTRO
    | self::PERFECT_AUDIENCE_ONLY
    | self::PERFECT_AUDIENCE_DIRECT;

    // ----------- FEATURES -----------

    // Analytics
    const ANALYTICS = 'analytics';
    const ADWORDS   = 'adwords';

    // Content
    const CONTENT   = 'content';
    const EMAIL     = 'email';
    const MEDIA     = 'media';
    const ABTESTS   = 'abtests';
    const PAGES     = 'pages';
    const PAGES_ALL = 'pages_all';

    // Forms
    const FORMS             = 'forms';
    const WEBEX             = 'webex';
    const REFERRALS         = 'referrals';
    const PROGRESSIVE_FORMS = 'progressive_forms';
    const TURN_OFF_AUTOFILL = 'turn_off_autofill';
    const FACEBOOK_LEAD_ADS = 'facebook_lead_ads';

    // Tracking
    const TRACKING        = 'tracking';
    const CAMPAIGNS       = 'campaigns';
    const VISITORID       = 'visitorid';
    const VISITORID_ALL   = 'visitorid_all';
    const VISITORID_EMAIL = 'visitor_id_email';

    // Automation
    const AUTOMATION       = 'automation';
    const AUTOMATION_ALL   = 'automation_all';
    const WORKFLOWS        = 'workflows';
    const VISUAL_WORKFLOWS = 'visual_workflows';
    const TASKS            = 'tasks';
    const LISTS            = 'lists';
    const PERSONAS         = 'personas';

    // Sales
    const SALES        = 'sales';
    const LEADS        = 'leads';
    const ACCOUNTS     = 'accounts';
    const PRODUCTS     = 'products';
    const PIPELINE     = 'pipeline';
    const SAVE_REPORTS = 'save_and_schedule_reports';

    // Contacts
    const ADVANCED_SEARCH     = 'advanced_search';
    const CONTACT_MANAGER     = 'contact_manager';
    const CUSTOM_FIELDS       = 'custom_fields';
    const LEAD_SCORING        = 'lead_scoring';
    const NOTIFY_WHEN_RETURNS = 'notify_when_returns_to_site';

    // E-Mail & Editor
    const BULK_EMAIL            = 'bulk_email';
    const EMAIL_DYNAMIC_CONTENT = 'email_dynamic_content';
    const EMAIL_OPENS           = 'email_opens';
    const EMAIL_SYNCING         = 'email_syncing';
    const OPTIMIZED_DELIVERY    = 'optimized_delivery';
    const EMAIL_JOB_REPORTS     = 'email_job_reports';
    const EMAIL_REPORTS         = 'email_reports';

    // Misc
    const PROJECTS         = 'projects';
    const API              = 'api';
    const SUPPORT          = 'support';
    const LITMUS           = 'litmus';
    const SOCIAL_LISTENING = 'social_listening';
    const SHUTTERSTOCK     = 'shutterstock';
    const USER_TEAMS       = 'user_teams';

    // Support
    const EMAIL_SUPPORT = 'email_support';
    const PHONE_SUPPORT = 'phone_support';

    // CRM Limitations
    const ADVANCED_SEARCH_LTD      = 'advanced_search_no_save';
    const BULK_EMAIL_150           = 'bulk_email_150';
    const BULK_EMAIL_500           = 'bulk_email_500';
    const BULK_EMAIL_75            = 'bulk_email_75';
    const CONTACT_MANAGER_25       = 'contact_manager_2.5_mil';
    const CUSTOM_FIELDS_20         = 'custom_fields_20';
    const EMAIL_OPENS_200          = 'email_opens_200';
    const EMAIL_SYNCING_LTD        = 'email_syncing_ltd';  // currently not known what the numerical limit will be
    const EMAIL_SEND_TO_LIST       = 'email_send_to_list';
    const LEAD_SCORING_LTD         = 'lead_scoring_no_custom_rules';
    const PIPELINE_LTD             = 'pipeline-limited';
    const RSS                      = 'rss';
    const SHOPPING_CART            = 'shopping_cart';
    const ECOMMERCE                = 'eCommerce'; // 3rd iteration of shopping cart
    const FROM_EMAIL_EDITABLE      = 'from_email_editable';
    const PERSONAS_NO_UPSELL       = 'personas-no-upsell';
    const SALES_REPORT_NO_SCHEDULE = 'sales_report_no_schedule';
    const SHOW_DKIM_VERIFICATION   = 'show_dkim_verification';
    const VALIDATE_EMAIL_DOMAIN    = 'validate_email_domain';

    // Utilization Score
    const UTILIZATION_SCORE = 'utilization_score';

    private $offering = 1;
    private $features = [];

    public static $offeringNames = [
        self::PRO                     => 'Marketing Automation',
        self::ESP                     => 'SharpSpring Mail',
        self::CRM                     => 'SharpSpring CRM', // May actually be sales features?
        self::SUP                     => 'Dedicated Support',
        self::VID                     => 'VisitorID',
        self::BETA                    => 'Beta',
        self::CRM_TIER_ZERO           => 'Free CRM',
        self::CRM_TIER_ZERO_INTRO     => 'Free CRM First 90 Days',
        self::CRM_TIER_ONE            => 'CRM Upgrade 1',
        self::CRM_TIER_TWO            => 'CRM Upgrade 2',
        self::PERFECT_AUDIENCE_ONLY   => 'Perfect Audience Only',
        self::PERFECT_AUDIENCE_DIRECT => 'Perfect Audience Direct',
    ];

    public const FLAG_ABBREVIATIONS = [
        self::PRO                     => 'PRO',
        self::ESP                     => 'ESP',
        self::CRM                     => 'CRM',
        self::SUP                     => 'SUP',
        self::VID                     => 'VID',
        self::BETA                    => 'BETA',
        self::CRM_TIER_ZERO           => 'CRM_TIER_ZERO',
        self::CRM_TIER_ZERO_INTRO     => 'CRM_TIER_ZERO_INTRO',
        self::CRM_TIER_ONE            => 'CRM_TIER_ONE',
        self::CRM_TIER_TWO            => 'CRM_TIER_TWO',
        self::PERFECT_AUDIENCE_ONLY   => 'PA_ONLY',
        self::PERFECT_AUDIENCE_DIRECT => 'PA_DIRECT',
    ];

    // Individual Limits / Settings Per Feature
    public static $featureSettings = [
        self::VISITORID  => ['anonymous' => 'invisible'],
        self::AUTOMATION => [
            'lists'               => 100, // cta added (modal)
            'tasks'               => 20,  // cta added (modal)
            'workflows'           => 20,  // cta added (modal)
            'multiRuleTasks'      => 2,   // cta added (inline)
            'multiEventWorkflows' => 2,   // cta added (inline)
            'rulesPerTask'        => 5,
            'eventsPerWorkflow'   => 5,
            'workflowsPerTask'    => 1,   // cta added (inline)
        ],
        self::PAGES      => [
            'funnels'       => 5,
            'funnelsAlert'  => [4],
            'funnelPages'   => 6,
            'articles'      => 20,
            'articlesAlert' => [5, 15],
            'upgradeLink'   => "http://sharpspring.com/business/ppc2/?utm=SSM01",
        ],
    ];

    //Offering::PRO vs Offering::$PRO_OFFERING, not confusing at all
    public static $PRO_OFFERING = [
        self::ABTESTS                => 1,
        self::ACCOUNTS               => 1,
        self::ADVANCED_SEARCH        => 1,
        self::ADWORDS                => 1,
        self::ANALYTICS              => 1,
        self::API                    => 1,
        self::AUTOMATION             => 1,
        self::AUTOMATION_ALL         => 1,
        self::BULK_EMAIL             => 1,
        self::CAMPAIGNS              => 1,
        self::CONTACT_MANAGER        => 1,
        self::CONTENT                => 1,
        self::CUSTOM_FIELDS          => 1,
        self::EMAIL                  => 1,
        self::EMAIL_JOB_REPORTS      => 1,
        self::EMAIL_OPENS            => 1,
        self::EMAIL_REPORTS          => 1,
        self::EMAIL_SUPPORT          => 1,
        self::EMAIL_SYNCING          => 1,
        self::EMAIL_SEND_TO_LIST     => 1,
        self::FORMS                  => 1,
        self::FACEBOOK_LEAD_ADS      => 1,
        self::LEAD_SCORING           => 1,
        self::LEADS                  => 1,
        self::LISTS                  => 1,
        self::LITMUS                 => 1,
        self::MEDIA                  => 1,
        self::NOTIFY_WHEN_RETURNS    => 1,
        self::OPTIMIZED_DELIVERY     => 1,
        self::PAGES                  => 1,
        self::PAGES_ALL              => 1,
        self::PERSONAS               => 1,
        self::PHONE_SUPPORT          => 1,
        self::PIPELINE               => 1,
        self::PRODUCTS               => 1,
        self::PROGRESSIVE_FORMS      => 1,
        self::REFERRALS              => 1,
        self::SALES                  => 1,
        self::SAVE_REPORTS           => 1,
        self::SHUTTERSTOCK           => 1,
        self::SOCIAL_LISTENING       => 1,
        self::TASKS                  => 1,
        self::TRACKING               => 1,
        self::TURN_OFF_AUTOFILL      => 1,
        self::USER_TEAMS             => 1,
        self::VISITORID              => 1,
        self::VISITORID_ALL          => 1,
        self::VISITORID_EMAIL        => 1,
        self::VISUAL_WORKFLOWS       => 1,
        self::WEBEX                  => 1,
        self::WORKFLOWS              => 1,
        self::UTILIZATION_SCORE      => 1,
        self::FROM_EMAIL_EDITABLE    => 1,
        self::RSS                    => 1,
        self::SHOPPING_CART          => 1,
        self::ECOMMERCE              => 1,
        self::EMAIL_DYNAMIC_CONTENT  => 1,
        self::SHOW_DKIM_VERIFICATION => 1,
        self::VALIDATE_EMAIL_DOMAIN  => 1,
    ];

    public static $BETA_OFFERING = [
        self::PROJECTS => 1,
    ];

    public static $ESP_OFFERING = [
        self::ABTESTS                => 1,
        self::ADVANCED_SEARCH        => 1,
        self::API                    => 1,
        self::AUTOMATION             => 1,
        self::BULK_EMAIL             => 1,
        self::CONTACT_MANAGER        => 1,
        self::CONTENT                => 1,
        self::CUSTOM_FIELDS          => 1,
        self::EMAIL                  => 1,
        self::EMAIL_JOB_REPORTS      => 1,
        self::EMAIL_OPENS            => 1,
        self::EMAIL_REPORTS          => 1,
        self::EMAIL_SUPPORT          => 1,
        self::EMAIL_SYNCING          => 1,
        self::EMAIL_SEND_TO_LIST     => 1,
        self::FORMS                  => 1,
        self::LEAD_SCORING           => 1,
        self::LISTS                  => 1,
        self::LITMUS                 => 1,
        self::NOTIFY_WHEN_RETURNS    => 1,
        self::OPTIMIZED_DELIVERY     => 1,
        self::PAGES                  => 1,
        self::PHONE_SUPPORT          => 1,
        self::PIPELINE               => 1,
        self::SAVE_REPORTS           => 1,
        self::SHUTTERSTOCK           => 1,
        self::SOCIAL_LISTENING       => 1,
        self::TASKS                  => 1,
        self::TRACKING               => 1,
        self::USER_TEAMS             => 1,
        self::VISITORID              => 1,
        self::VISITORID_EMAIL        => 1,
        self::VISUAL_WORKFLOWS       => 1,
        self::WORKFLOWS              => 1,
        self::FROM_EMAIL_EDITABLE    => 1,
        self::RSS                    => 1,
        self::SHOPPING_CART          => 1,
        self::PERSONAS_NO_UPSELL     => 1,
        self::SHOW_DKIM_VERIFICATION => 1,
        self::VALIDATE_EMAIL_DOMAIN  => 1,
    ];

    public static $SUP_OFFERING = [
        self::SUPPORT => 1,
    ];

    public static $CRM_OFFERING = [
        self::SALES               => 1,
        self::LEADS               => 1,
        self::ACCOUNTS            => 1,
        self::LEAD_SCORING_LTD    => 1,
        self::NOTIFY_WHEN_RETURNS => 1,
    ];

    public static $VID_OFFERING = [
        self::TRACKING  => 1,
        self::ANALYTICS => 1,
        self::VISITORID => 1,
    ];

    public static $CRM_TIER_ZERO_OFFERING = [
        self::ACCOUNTS                 => 1,
        self::ADVANCED_SEARCH_LTD      => 1,
        self::ANALYTICS                => 1,
        self::API                      => 1,
        self::BULK_EMAIL_75            => 1,
        self::CONTACT_MANAGER_25       => 1,
        self::CONTENT                  => 1,
        self::CUSTOM_FIELDS_20         => 1,
        self::EMAIL                    => 1,
        self::EMAIL_OPENS_200          => 1,
        self::EMAIL_SYNCING_LTD        => 1,
        self::FORMS                    => 1,
        self::LEAD_SCORING_LTD         => 1,
        self::LEADS                    => 1,
        self::PIPELINE_LTD             => 1,
        self::PRODUCTS                 => 1,
        self::PROGRESSIVE_FORMS        => 1,
        self::SALES                    => 1,
        self::TRACKING                 => 1,
        self::VISITORID                => 1,
        self::EMAIL_DYNAMIC_CONTENT    => 1,
        self::SALES_REPORT_NO_SCHEDULE => 1,
        self::SOCIAL_LISTENING         => 1,
        self::NOTIFY_WHEN_RETURNS      => 1,
    ];

    public static $CRM_TIER_ZERO_INTRO_OFFERING = [
        self::ACCOUNTS              => 1,
        self::ADVANCED_SEARCH_LTD   => 1,
        self::ANALYTICS             => 1,
        self::API                   => 1,
        self::BULK_EMAIL_500        => 1,
        self::CONTACT_MANAGER_25    => 1,
        self::CONTENT               => 1,
        self::CUSTOM_FIELDS_20      => 1,
        self::EMAIL                 => 1,
        self::EMAIL_JOB_REPORTS     => 1,
        self::EMAIL_OPENS           => 1,
        self::EMAIL_REPORTS         => 1,
        self::EMAIL_SUPPORT         => 1,
        self::EMAIL_SYNCING_LTD     => 1,
        self::FORMS                 => 1,
        self::LEAD_SCORING          => 1,
        self::LEADS                 => 1,
        self::PIPELINE_LTD          => 1,
        self::PRODUCTS              => 1,
        self::PROGRESSIVE_FORMS     => 1,
        self::SALES                 => 1,
        self::SAVE_REPORTS          => 1,
        self::TRACKING              => 1,
        self::USER_TEAMS            => 1,
        self::VISITORID             => 1,
        self::VISITORID_ALL         => 1,
        self::VISITORID_EMAIL       => 1,
        self::EMAIL_DYNAMIC_CONTENT => 1,
        self::SOCIAL_LISTENING      => 1,
        self::NOTIFY_WHEN_RETURNS   => 1,
    ];

    public static $CRM_TIER_ONE_OFFERING = [
        self::ACCOUNTS              => 1,
        self::ADVANCED_SEARCH       => 1,
        self::ANALYTICS             => 1,
        self::API                   => 1,
        self::BULK_EMAIL_150        => 1,
        self::CONTACT_MANAGER_25    => 1,
        self::CONTENT               => 1,
        self::CUSTOM_FIELDS_20      => 1,
        self::EMAIL                 => 1,
        self::EMAIL_JOB_REPORTS     => 1,
        self::EMAIL_OPENS           => 1,
        self::EMAIL_SUPPORT         => 1,
        self::EMAIL_SYNCING_LTD     => 1,
        self::FORMS                 => 1,
        self::LEAD_SCORING          => 1,
        self::LEADS                 => 1,
        self::PIPELINE_LTD          => 1,
        self::PRODUCTS              => 1,
        self::PROGRESSIVE_FORMS     => 1,
        self::SALES                 => 1,
        self::SAVE_REPORTS          => 1,
        self::TRACKING              => 1,
        self::USER_TEAMS            => 1,
        self::VISITORID             => 1,
        self::VISITORID_ALL         => 1,
        self::VISITORID_EMAIL       => 1,
        self::EMAIL_DYNAMIC_CONTENT => 1,
        self::SOCIAL_LISTENING      => 1,
        self::NOTIFY_WHEN_RETURNS   => 1,
    ];

    public static $CRM_TIER_TWO_OFFERING = [
        self::ACCOUNTS              => 1,
        self::ADVANCED_SEARCH       => 1,
        self::ANALYTICS             => 1,
        self::API                   => 1,
        self::BULK_EMAIL_500        => 1,
        self::CONTACT_MANAGER       => 1,
        self::CONTENT               => 1,
        self::EMAIL                 => 1,
        self::EMAIL_JOB_REPORTS     => 1,
        self::EMAIL_OPENS           => 1,
        self::EMAIL_REPORTS         => 1,
        self::EMAIL_SUPPORT         => 1,
        self::EMAIL_SYNCING_LTD     => 1,
        self::FORMS                 => 1,
        self::LEAD_SCORING          => 1,
        self::LEADS                 => 1,
        self::PIPELINE              => 1,
        self::PRODUCTS              => 1,
        self::PROGRESSIVE_FORMS     => 1,
        self::SALES                 => 1,
        self::SAVE_REPORTS          => 1,
        self::TRACKING              => 1,
        self::USER_TEAMS            => 1,
        self::VISITORID             => 1,
        self::VISITORID_ALL         => 1,
        self::VISITORID_EMAIL       => 1,
        self::EMAIL_DYNAMIC_CONTENT => 1,
        self::SOCIAL_LISTENING      => 1,
        self::NOTIFY_WHEN_RETURNS   => 1,
    ];

    public static $PERFECT_AUDIENCE_ONLY_OFFERING = [
        self::TRACKING => 1,
    ];

    public static $PERFECT_AUDIENCE_DIRECT_OFFERING = [
        self::TRACKING => 1,
    ];

    public static function offerings()
    {
        return [
            static::PRO                     => static::$PRO_OFFERING,
            static::ESP                     => static::$ESP_OFFERING,
            static::CRM                     => static::$CRM_OFFERING,
            static::VID                     => static::$VID_OFFERING,
            static::SUP                     => static::$SUP_OFFERING,
            static::BETA                    => static::$BETA_OFFERING,
            static::CRM_TIER_ZERO           => static::$CRM_TIER_ZERO_OFFERING,
            static::CRM_TIER_ZERO_INTRO     => static::$CRM_TIER_ZERO_INTRO_OFFERING,
            static::CRM_TIER_ONE            => static::$CRM_TIER_ONE_OFFERING,
            static::CRM_TIER_TWO            => static::$CRM_TIER_TWO_OFFERING,
            static::PERFECT_AUDIENCE_ONLY   => static::$PERFECT_AUDIENCE_ONLY_OFFERING,
            static::PERFECT_AUDIENCE_DIRECT => static::$PERFECT_AUDIENCE_DIRECT_OFFERING,
        ];
    }

    public function __construct($offering)
    {
        if (is_array($offering)) {
            $offering = $offering['productOffering'];
        }

        $this->offering = $offering ?: static::PRO;
        foreach (static::offerings() as $offer => $features) {
            if ($offer & $this->offering) {
                $this->features = array_merge($this->features, $features);
            }
        }
    }

    public function getOffering()
    {
        return $this->offering;
    }

    public function hasOffering($offering)
    {
        return (bool)($this->offering & $offering);
    }

    /**
     * @param int $offering
     *
     * @return bool
     */
    public function canViewOffering($offering)
    {
        return $this->hasOffering($offering) || $this->isCrm();
    }

    public static function offeringHasFeature($offering, $feature)
    {
        return array_key_exists($feature, $offering->features);
    }

    /**
     * @param string $feature
     *
     * @return bool
     *
     * @deprecated use hasFeature instead
     */
    public function has($feature)
    {
        return static::offeringHasFeature($this, $feature);
    }

    /**
     * @param string $feature
     *
     * @return bool
     */
    public function hasFeature(string $feature): bool
    {
        return static::offeringHasFeature($this, $feature);
    }

    /**
     * Determines whether we should _display_ a feature for the offering.
     * We display all the features to FreeCRM but prompt for upgrade when attempting to use some.
     *
     * @param string $feature
     *
     * @return bool
     */
    public function canViewFeature($feature)
    {
        return $this->hasFeature($feature) || $this->isCrm();
    }

    /**
     * @return bool
     */
    public function isCrm()
    {
        return $this->hasOffering(
            static::CRM_TIER_ZERO
            | static::CRM_TIER_ZERO_INTRO
            | static::CRM_TIER_ONE
            | static::CRM_TIER_TWO
        );
    }

    /**
     * @return bool
     */
    public function isPaOnly()
    {
        return $this->hasOffering(
            static::PERFECT_AUDIENCE_ONLY
        );
    }

    /**
     * @return bool
     */
    public function isPaDirect()
    {
        return $this->hasOffering(
            static::PERFECT_AUDIENCE_DIRECT
        );
    }

    /**
     * @return bool
     */
    public function isPaOnlyOrDirect()
    {
        return $this->hasOffering(
            static::PERFECT_AUDIENCE_ONLY
            | static::PERFECT_AUDIENCE_DIRECT
        );
    }

    public function getFeatures()
    {
        return $this->features;
    }

    public function getFeatureSettings($feature = null)
    {
        if (is_null($feature)) {
            return static::$featureSettings;
        }
        if ($this->hasFeature($feature) && isset(static::$featureSettings[$feature])) {
            return static::$featureSettings[$feature];
        }

        return null;
    }

    public function getOfferingName()
    {
        $offeringList = [];
        foreach (static::$offeringNames as $offering => $name) {
            if ($this->hasOffering($offering)) {
                $offeringList [] = $name;
            }
        }

        return implode(', ', $offeringList);
    }

    public function getPrimaryOffering()
    {
        foreach (static::ALL_OFFERINGS as $offering) {
            if ($this->offering & static::PRIMARY_OFFERING_MASK & $offering) {
                return $offering;
            }
        }

        return null;
    }

    public function toggleProductOffering()
    {
        $this->offering = $this->offering ^ (static::PRO | static::ESP);

        return $this->offering;
    }

    /**
     * @param int $offering Offering bitmask value
     *
     * @return string[]
     */
    public static function offeringToAbbreviationArray($offering)
    {
        $members = [];
        foreach (static::FLAG_ABBREVIATIONS as $offeringID => $abbreviation) {
            if ($offering & $offeringID) {
                $members [] = $abbreviation;
            }
        }

        return $members;
    }
}
