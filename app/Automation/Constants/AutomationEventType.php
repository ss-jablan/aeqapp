<?php

namespace App\Automation\Constants;

class AutomationEventType
{
    /**
     * @var string
     */
    const CREATE_OPPORTUNITY = 'createOpportunity';
    /**
     * @var string
     */
    const CHANGE_OPPORTUNITY_FIELD = 'changeOpportunityField';
    /**
     * @var string
     */
    const CHANGE_OPPORTUNITY_STATUS = 'changeOpportunityStatus';
    /**
     * @var string
     */
    const ASSIGN_OPPORTUNITY_OWNER = 'assignOpportunityOwner';
    /**
     * @var string
     */
    const CREATE_TASK = 'createTask';
    /**
     * @var string
     */
    const SEND_EMAIL = 'sendEmail';
    /**
     * @var string
     */
    const SEND_EMAIL_TO_REFERRER = 'sendEmailToReferrer';
    /**
     * @var string
     */
    const SEND_NOTIFICATION = 'sendNotification';
    /**
     * @var string
     */
    const SEND_EMAIL_TO_LIST = 'sendEmailToList';
    /**
     * @var string
     */
    const SEND_ONE_OFF_EMAIL = 'sendOneOffEmail';
    /**
     * @var string
     */
    const SEND_NOTIFICATION_EMAIL = 'sendNotificationEmail';
    /**
     * @var string
     */
    const SEND_NOTIFICATION_EMAIL_TO_REFERRER = 'sendNotificationEmailToReferrer';
    /**
     * @var string
     */
    const POSTBACK_LEAD = 'postBackLead';
    /**
     * @var string
     */
    const POSTBACK_OPPORTUNITY = 'postBackOpportunity';
    /**
     * @var string
     */
    const ADD_TO_LIST = 'addToList';
    /**
     * @var string
     */
    const ADD_TO_LISTS_WITH_TAG = 'addToListsWithTag';
    /**
     * @var string
     */
    const REMOVE_FROM_LIST = 'removeFromList';
    /**
     * @var string
     */
    const REMOVE_FROM_LISTS_WITH_TAG = 'removeFromListsWithTag';
    /**
     * @var string
     */
    const ASSIGN_LEAD_CAMPAIGN = 'assignLeadCampaign';
    /**
     * @var string
     */
    const ASSIGN_LEAD_OWNER = 'assignLeadOwner';
    /**
     * @var string
     */
    const CHANGE_LEAD_FIELD = 'changeLeadField';
    /**
     * @var string
     */
    const INCREMENT_COUNTER_FIELD = 'incrementCounterField';
    /**
     * @var string
     */
    const DECREMENT_COUNTER_FIELD = 'decrementCounterField';
    /**
     * @var string
     */
    const CHANGE_LEAD_STATUS = 'changeLeadStatus';
    /**
     * @var string
     */
    const CHANGE_LEAD_PERSONA = 'changeLeadPersona';
    /**
     * @var string
     */
    const REMOVE_FROM_ACTION_GROUP = 'removeFromActionGroup';
    /**
     * @var string
     */
    const REMOVE_FROM_VISUAL_WORKFLOW = 'removeFromVisualWorkflow';
    /**
     * @var string
     */
    const CONVERSION_GOAL_MET = 'markConversionGoalMet';
    /**
     * @var string
     */
    const ADD_LEAD_TAG = 'addTagToLead';
    /**
     * @var string
     */
    const REMOVE_LEAD_TAG = 'removeTagFromLead';
    /**
     * @var string
     */
    const TEST_EVENT = 'test';
    /**
     * @var string
     */
    const YESNO_BRANCH = 'yesNoBranch';
    /**
     * @var string
     */
    const SOCIAL_INVITE = 'socialInvite';
    /**
     * @var string
     */
    const RSS_EMAIL = 'rssEmail';
    /**
     * @var string
     */
    const ADD_TO_WORKFLOW = 'addToWorkflow';
    /**
     * @var string
     */
    const ADD_TO_ACTION_GROUP = 'addToActionGroup';
    /**
     * @var string
     */
    const ADD_TO_VISUAL_WORKFLOW = 'addToVisualWorkflow';
    /**
     * @var string
     */
    const CHANGE_OPPORTUNITY_STAGE = 'changeOpportunityStage';
    /**
     * @var string
     */
    const REMOVE_FROM_WORKFLOW = 'removeFromWorkflow';
    /**
     * @var string
     */
    const REMOVE_FROM_OPPORTUNITY_WORKFLOW = 'removeFromOpportunityWorkflow';
}
