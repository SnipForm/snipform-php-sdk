<?php

namespace SnipForm\Query;

/**
 * Every queryable field on a SignalSession, mirroring the server-side
 * `SignalFieldMappingSet`. Pass cases into the Builder's `where*()` methods
 * for IDE-discoverable, type-checked queries:
 *
 *   $snipform->signals()
 *       ->where(SessionField::COUNTRY, 'US')
 *       ->whereBetween(SessionField::TIME_ON_SITE, 60, 300)
 *       ->whereStartsWith(SessionField::ENTRY_PATH, '/blog');
 *
 * `type()` powers Builder-side validation: numeric ops on a KEYWORD field
 * (or vice versa) throw before the request leaves the SDK.
 *
 * The set is hand-curated against the server's catalog. Additions are rare;
 * sync when SignalFieldMappingSet changes.
 */
enum SessionField: string
{
    // ----------------------------------------------------------------------
    // Entry page
    // ----------------------------------------------------------------------
    case ENTRY_SUBDOMAIN = 'entry_subdomain';
    case ENTRY_URL = 'entry_url';
    case ENTRY_PATH = 'entry_path';
    case ENTRY_PATH_FULL = 'entry_path_full';
    case ENTRY_TITLE = 'entry_title';

    // ----------------------------------------------------------------------
    // Exit page
    // ----------------------------------------------------------------------
    case EXIT_SUBDOMAIN = 'exit_subdomain';
    case EXIT_URL = 'exit_url';
    case EXIT_PATH = 'exit_path';
    case EXIT_PATH_FULL = 'exit_path_full';
    case EXIT_TITLE = 'exit_title';

    // ----------------------------------------------------------------------
    // Referrer
    // ----------------------------------------------------------------------
    case REFERRER_URL = 'referrer_url';
    case REFERRER_DOMAIN = 'referrer_domain';

    // ----------------------------------------------------------------------
    // Pages visited (any in-session, not just entry/exit)
    // ----------------------------------------------------------------------
    case PAGE_URL = 'page_url';
    case PAGE_PATH = 'page_path';
    case PAGE_COUNT = 'page_count';

    // ----------------------------------------------------------------------
    // Tags
    // ----------------------------------------------------------------------
    case TAGS_KEY = 'tags_key';
    case TAGS_VALUE = 'tags_value';

    // ----------------------------------------------------------------------
    // Geo
    // ----------------------------------------------------------------------
    case COUNTRY = 'country';
    case COUNTRY_CODE = 'country_code';
    case CONTINENT = 'continent';
    case REGION = 'region';
    case REGION_CODE = 'region_code';
    case CITY = 'city';
    case CITY_CODE = 'city_code';
    case POSTAL_CODE = 'postal_code';
    case TIMEZONE = 'timezone';
    case LANGUAGE = 'language';

    // ----------------------------------------------------------------------
    // Browser / Device / OS
    // ----------------------------------------------------------------------
    case BROWSER_AGENT = 'browser_agent';
    case BROWSER = 'browser';
    case BROWSER_ENGINE = 'browser_engine';
    case BROWSER_FAMILY = 'browser_family';
    case DEVICE = 'device';
    case DEVICE_BRAND = 'device_brand';
    case DEVICE_MODEL = 'device_model';
    case OS = 'os';
    case OS_VERSION = 'os_version';

    // ----------------------------------------------------------------------
    // Bot detection
    // ----------------------------------------------------------------------
    case BOT_AGENT = 'bot_agent';
    case BOT_IP = 'bot_ip';
    case BOT_NAME = 'bot_name';
    case BOT_CATEGORY = 'bot_category';
    case BOT_VERIFIED = 'bot_verified';

    // ----------------------------------------------------------------------
    // Acquisition / Channel attribution
    // ----------------------------------------------------------------------
    case CHANNEL = 'channel';
    case CHANNEL_NAME = 'channel_name';
    case CHANNEL_METHOD = 'channel_method';
    case CHANNEL_CLICK_ID = 'channel_click_id';
    case CHANNEL_CUSTOM_RULE = 'channel_custom_rule';
    case UTM_SOURCE = 'utm_source';
    case UTM_MEDIUM = 'utm_medium';
    case UTM_CAMPAIGN = 'utm_campaign';
    case UTM_CONTENT = 'utm_content';
    case UTM_TERM = 'utm_term';
    case SOURCE_ID = 'source_id';
    case SOURCE = 'source';
    case SOURCE_ICON = 'source_icon';

    // ----------------------------------------------------------------------
    // Acquisition value
    // ----------------------------------------------------------------------
    case ACQUISITION_COST = 'acquisition_cost';
    case ACQUISITION_VALUE = 'acquisition_value';
    case ACQUISITION_CURRENCY_CODE = 'acquisition_currency_code';
    case ACQUISITION_TAGS = 'acquisition_tags';

    // ----------------------------------------------------------------------
    // Short links
    // ----------------------------------------------------------------------
    case SHORT_LINK_ID = 'short_link_id';
    case SHORT_LINK_GROUP_ID = 'short_link_group_id';
    case SHORT_LINK_CLICK_ID = 'short_link_click_id';

    // ----------------------------------------------------------------------
    // Forms
    // ----------------------------------------------------------------------
    case SNIP_FORM_ID = 'snip_form_id';
    case SNIP_FORM_SUBMIT_ID = 'snip_form_submit_id';

    // ----------------------------------------------------------------------
    // Events
    // ----------------------------------------------------------------------
    case EVENT_NAME = 'event_name';
    case EVENT_VALUE = 'event_value';
    case EVENT_VALUE_NUM = 'event_value_num';

    // ----------------------------------------------------------------------
    // Session metrics
    // ----------------------------------------------------------------------
    case BOUNCED = 'bounced';
    case ENTRY_TS = 'entry_ts';
    case LAST_TS = 'last_ts';
    case TIME_ON_SITE = 'time_on_site';
    case AVG_MAX_SCROLL = 'avg_max_scroll';
    case VIEWS = 'views';
    case ACCOUNT_SEQ = 'account_seq';
    case SCREEN_WIDTH = 'screen_width';
    case SCREEN_HEIGHT = 'screen_height';
    case LAST_PAGE = 'last_page';

    /**
     * Data-type bucket. Mirrors the server's `SignalFieldMappingSet` `type`.
     */
    public function type(): FieldType
    {
        return match ($this) {
            self::SCREEN_WIDTH,
            self::SCREEN_HEIGHT,
            self::PAGE_COUNT,
            self::ENTRY_TS,
            self::LAST_TS,
            self::TIME_ON_SITE,
            self::VIEWS,
            self::ACCOUNT_SEQ,
            self::ACQUISITION_COST,
            self::ACQUISITION_VALUE => FieldType::INT,

            self::AVG_MAX_SCROLL,
            self::EVENT_VALUE_NUM => FieldType::FLOAT,

            self::BOUNCED,
            self::BOT_VERIFIED => FieldType::BOOL,

            // Everything else is keyword (strings, paths, urls, ids, ...).
            default => FieldType::KEYWORD,
        };
    }
}
