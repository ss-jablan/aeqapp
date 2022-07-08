<?php

namespace App\Automation;

use App\Automation\Constants\AutomationEventType;

class AutomationEventMap extends AutomationEventType
{
    /**
     * @var string[]
     */
    public static $events = [
        self::SEND_EMAIL,
        self::SEND_EMAIL_TO_REFERRER,
        self::SEND_ONE_OFF_EMAIL,
        self::SEND_EMAIL_TO_LIST,
        self::SEND_NOTIFICATION,
        self::SEND_NOTIFICATION_EMAIL,
        self::SEND_NOTIFICATION_EMAIL_TO_REFERRER,
        self::ADD_TO_WORKFLOW,
        self::ADD_TO_ACTION_GROUP,
        self::ADD_TO_VISUAL_WORKFLOW,
        self::REMOVE_FROM_WORKFLOW,
        self::REMOVE_FROM_ACTION_GROUP,
        self::REMOVE_FROM_VISUAL_WORKFLOW,
        self::REMOVE_FROM_OPPORTUNITY_WORKFLOW,
        self::ASSIGN_LEAD_CAMPAIGN,
        self::ASSIGN_LEAD_OWNER,
        self::CHANGE_LEAD_FIELD,
        self::CHANGE_OPPORTUNITY_FIELD,
        self::CHANGE_LEAD_PERSONA,
        self::INCREMENT_COUNTER_FIELD,
        self::DECREMENT_COUNTER_FIELD,
        self::ADD_TO_LIST,
        self::REMOVE_FROM_LIST,
        self::ADD_TO_LISTS_WITH_TAG,
        self::REMOVE_FROM_LISTS_WITH_TAG,
        self::CHANGE_LEAD_STATUS,
        self::POSTBACK_LEAD,
        self::SOCIAL_INVITE,
        self::RSS_EMAIL,
        self::CREATE_TASK,
        self::CHANGE_OPPORTUNITY_STAGE,
        self::CREATE_OPPORTUNITY,
        self::CHANGE_OPPORTUNITY_STATUS,
        self::ASSIGN_OPPORTUNITY_OWNER,
        self::CONVERSION_GOAL_MET,
        self::ADD_LEAD_TAG,
        self::REMOVE_LEAD_TAG,
        self::TEST_EVENT,
        self::YESNO_BRANCH,
        self::POSTBACK_OPPORTUNITY,
    ];
    /**
     * @var string[]
     */
    private static $emailEvents = [
        self::SEND_EMAIL,
        self::SEND_EMAIL_TO_REFERRER,
        self::SEND_ONE_OFF_EMAIL,
        self::SEND_EMAIL_TO_LIST,
        self::SEND_NOTIFICATION,
        self::SEND_NOTIFICATION_EMAIL,
        self::SEND_NOTIFICATION_EMAIL_TO_REFERRER,
        self::RSS_EMAIL,
    ];

    /**
     * isEmailEvent
     *
     * @param string $eventType
     *
     * @return bool
     */
    public static function isEmailEvent(string $eventType): bool
    {
        return in_array($eventType, static::$emailEvents);
    }

    /**
     * @param string $eventType
     *
     * @return bool
     */
    public static function validEvent(string $eventType): bool
    {
        return in_array($eventType, static::$events);
    }
}
