<?php
namespace core_question\event_observers;

class question_observer {
    /**
     * Calls the possibly overridden question options cache item
     * invalidation function.
     *
     * @param \core\event\question_updated $event
     * @throws \dml_exception
     */
    public static function remove_from_options_cache(\core\event\question_updated $event): void {
        global $DB, $CFG;

        $qtype = $DB->get_field($event->objecttable, 'qtype', ['id' => $event->objectid]);

        $function = "qtype_$qtype::remove_from_cache";
        $includepath = "$CFG->dirroot/question/type/$qtype/questiontype.php";

        $questionobject = new \stdClass();
        $questionobject->id = $event->objectid;

        require_once($includepath);
        call_user_func($function, $questionobject);
    }
}
