<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduled and adhoc task management.
 *
 * @package    core
 * @category   task
 * @copyright  2024 Damyon Wiese, Darren Cocco
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

/**
 * Collection of task related methods.
 *
 * Some locking rules for this class:
 * All changes to scheduled tasks must be protected with both - the global cron lock and the lock
 * for the specific scheduled task (in that order). Locks must be released in the reverse order.
 * @copyright  2024 Damyon Wiese, Darren Cocco
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /**
     * @var int Used to tell the adhoc task queue to fairly distribute tasks.
     */
    const ADHOC_TASK_QUEUE_MODE_DISTRIBUTING = 0;

    /**
     * @var int Used to tell the adhoc task queue to try and fill unused capacity.
     */
    const ADHOC_TASK_QUEUE_MODE_FILLING = 1;

    /**
     * @var int Used to set the retention period for adhoc tasks that have failed and to be cleaned up.
     * The number is in week unit. The default value is 4 weeks.
     */
    const ADHOC_TASK_FAILED_RETENTION = 4 * WEEKSECS;

    /**
     * Class name of the task manager in use.
     * @var string
     */
    protected static $taskmanager = null;

    /**
     * Returns the class name of the configured task manager implementation.
     *
     * Defaults to the old cron task manager if none supplied.
     *
     * @throws \coding_exception
     * @return manager_interface::class
     */
    protected static function get_manager_class() {
        global $CFG;
        if (is_null(self::$taskmanager)) {
            if (isset($CFG->taskmanager)) {
                $refclass = new \ReflectionClass($CFG->taskmanager);
                if ($refclass->implementsInterface(manager_interface::class)) {
                    self::$taskmanager = $CFG->taskmanager;
                } else {
                    throw new \coding_exception("Defined task manager doesn't implement core\\task\\manager_interface");
                }
            } else {
                self::$taskmanager = manager_default::class;
            }
        }
        return self::$taskmanager;
    }

    static function reset_state(): void {
        self::get_manager_class()::reset_state();
    }

    /**
     * Given a component name, will load the list of tasks in the db/tasks.php file for that component.
     *
     * @param string $componentname - The name of the component to fetch the tasks for.
     * @param bool $expandr - if true (default) an 'R' value in a time is expanded to an appropriate int.
     *      If false, they are left as 'R'
     * @return \core\task\scheduled_task[] - List of scheduled tasks for this component.
     */
    static function load_default_scheduled_tasks_for_component($componentname, $expandr = true) {
        return self::get_manager_class()::load_default_scheduled_tasks_for_component($componentname, $expandr);
    }

    /**
     * Update the datastore to contain a list of scheduled task for a component.
     * The list of scheduled tasks is taken from @load_scheduled_tasks_for_component.
     * Will throw exceptions for any errors.
     *
     * @param string $componentname - The frankenstyle component name.
     */
    static function reset_scheduled_tasks_for_component($componentname) {
        self::get_manager_class()::reset_scheduled_tasks_for_component($componentname);
    }

    /**
     * Schedule a new task, or reschedule an existing adhoc task which has matching data.
     *
     * Only a task matching the same user, classname, component, and customdata will be rescheduled.
     * If these values do not match exactly then a new task is scheduled.
     *
     * @param \core\task\adhoc_task $task - The new adhoc task information to store.
     * @since Moodle 3.7
     */
    static function reschedule_or_queue_adhoc_task(adhoc_task $task): void {
        self::get_manager_class()::reschedule_or_queue_adhoc_task($task);
    }

    /**
     * Queue an adhoc task to run in the background.
     *
     * @param \core\task\adhoc_task $task - The new adhoc task information to store.
     * @param bool $checkforexisting - If set to true and the task with the same user, classname, component and customdata
     *     is already scheduled then it will not schedule a new task. Can be used only for ASAP tasks.
     * @return boolean - True if the config was saved.
     */
    static function queue_adhoc_task(adhoc_task $task, $checkforexisting = false) {
        return self::get_manager_class()::queue_adhoc_task($task, $checkforexisting);
    }

    /**
     * Change the default configuration for a scheduled task.
     * The list of scheduled tasks is taken from {@link load_scheduled_tasks_for_component}.
     *
     * @param \core\task\scheduled_task $task - The new scheduled task information to store.
     * @return boolean - True if the config was saved.
     */
    static function configure_scheduled_task(scheduled_task $task) {
        return self::get_manager_class()::configure_scheduled_task($task);
    }

    /**
     * Utility method to create a DB record from a scheduled task.
     *
     * @param \core\task\scheduled_task $task
     * @return \stdClass
     */
    static function record_from_scheduled_task($task) {
        return self::get_manager_class()::record_from_scheduled_task($task);
    }

    /**
     * Utility method to create a DB record from an adhoc task.
     *
     * @param \core\task\adhoc_task $task
     * @return \stdClass
     */
    static function record_from_adhoc_task($task) {
        return self::get_manager_class()::record_from_adhoc_task($task);
    }

    /**
     * Utility method to create an adhoc task from a DB record.
     *
     * @param \stdClass $record
     * @return \core\task\adhoc_task
     * @throws \moodle_exception
     */
    static function adhoc_task_from_record($record) {
        return self::get_manager_class()::adhoc_task_from_record($record);
    }

    /**
     * Utility method to create a task from a DB record.
     *
     * @param \stdClass $record
     * @param bool $expandr - if true (default) an 'R' value in a time is expanded to an appropriate int.
     *      If false, they are left as 'R'
     * @param bool $override - if true loads overridden settings from config.
     * @return \core\task\scheduled_task|false
     */
    static function scheduled_task_from_record($record, $expandr = true, $override = true) {
        return self::get_manager_class()::scheduled_task_from_record($record, $expandr, $override);
    }

    /**
     * Given a component name, will load the list of tasks from the scheduled_tasks table for that component.
     * Do not execute tasks loaded from this function - they have not been locked.
     * @param string $componentname - The name of the component to load the tasks for.
     * @return \core\task\scheduled_task[]
     */
    static function load_scheduled_tasks_for_component($componentname) {
        return self::get_manager_class()::load_scheduled_tasks_for_component($componentname);
    }

    /**
     * This function load the scheduled task details for a given classname.
     *
     * @param string $classname
     * @return \core\task\scheduled_task or false
     */
    static function get_scheduled_task($classname) {
        return self::get_manager_class()::get_scheduled_task($classname);
    }

    /**
     * This function load the adhoc tasks for a given classname.
     *
     * @param string $classname
     * @param bool $failedonly
     * @param bool $skiprunning do not return tasks that are in the running state
     * @return array
     */
    static function get_adhoc_tasks(string $classname, bool $failedonly = false, bool $skiprunning = false): array {
        return self::get_manager_class()::get_adhoc_tasks($classname, $failedonly, $skiprunning);
    }

    /**
     * This function returns adhoc tasks summary per component classname
     *
     * @return array
     */
    static function get_adhoc_tasks_summary(): array {
        return self::get_manager_class()::get_adhoc_tasks_summary();
    }

    /**
     * This function load the default scheduled task details for a given classname.
     *
     * @param string $classname
     * @param bool $expandr - if true (default) an 'R' value in a time is expanded to an appropriate int.
     *      If false, they are left as 'R'
     * @return \core\task\scheduled_task|false
     */
    static function get_default_scheduled_task($classname, $expandr = true) {
        return self::get_manager_class()::get_default_scheduled_task($classname, $expandr);
    }

    /**
     * This function will return a list of all the scheduled tasks that exist in the database.
     *
     * @return \core\task\scheduled_task[]
     */
    public static function get_all_scheduled_tasks() {
        return self::get_manager_class()::get_all_scheduled_tasks();
    }

    /**
     * This function will return a list of all adhoc tasks that have a faildelay
     *
     * @param int $delay filter how long the task has been delayed
     * @return \core\task\adhoc_task[]
     */
    public static function get_failed_adhoc_tasks(int $delay = 0): array {
        return self::get_manager_class()::get_failed_adhoc_tasks($delay);
    }

    /**
     * @deprecated since Moodle 4.1 MDL-67648
     */
    #[\core\attribute\deprecated('\core\task\manager::get_next_adhoc_task()', since: '4.1', mdl: 'MDL-67648', final: true)]
    public static function ensure_adhoc_task_qos(): void {
        \core\deprecation::emit_deprecation_if_present([self::class, __FUNCTION__]);
    }

    /**
     * This function will dispatch the next adhoc task in the queue. The task will be handed out
     * with an open lock - possibly on the entire cron process. Make sure you call either
     * {@link adhoc_task_failed} or {@link adhoc_task_complete} to release the lock and reschedule the task.
     *
     * @param int $timestart
     * @param bool $checklimits Should we check limits?
     * @param string|null $classname Return only task of this class
     * @return \core\task\adhoc_task|null
     * @throws \moodle_exception
     */
    public static function get_next_adhoc_task(int $timestart, ?bool $checklimits = true, ?string $classname = null): ?adhoc_task {
        return self::get_manager_class()::get_next_adhoc_task($timestart, $checklimits, $classname);
    }

    /**
     * Return a list of candidate adhoc tasks to run.
     *
     * @param int $timestart Only return tasks where nextruntime is less than this value
     * @param int $limit Limit the list to this many results
     * @param int|null $runmax Only return tasks that have less than this value currently running
     * @param array $pertasklimits An array of classname => limit specifying how many instance of a task may be returned
     * @return array Array of candidate tasks
     */
    public static function get_candidate_adhoc_tasks(
        int $timestart,
        int $limit,
        ?int $runmax,
        array $pertasklimits = []
    ): array {
        return self::get_manager_class()::get_candidate_adhoc_tasks($timestart, $limit, $runmax, $pertasklimits);
    }

    /**
     * This function will get an adhoc task by id. The task will be handed out
     * with an open lock - possibly on the entire cron process. Make sure you call either
     * {@see ::adhoc_task_failed} or {@see ::adhoc_task_complete} to release the lock and reschedule the task.
     *
     * @param int $taskid
     * @return \core\task\adhoc_task|null
     * @throws \moodle_exception
     */
    public static function get_adhoc_task(int $taskid): ?adhoc_task {
        return self::get_manager_class()::get_adhoc_task($taskid);
    }

    /**
     * This function will dispatch the next scheduled task in the queue. The task will be handed out
     * with an open lock - possibly on the entire cron process. Make sure you call either
     * {@link scheduled_task_failed} or {@link scheduled_task_complete} to release the lock and reschedule the task.
     *
     * @param int $timestart - The start of the cron process - do not repeat any tasks that have been run more recently than this.
     * @return \core\task\scheduled_task or null
     * @throws \moodle_exception
     */
    public static function get_next_scheduled_task($timestart) {
        return self::get_manager_class()::get_next_scheduled_task($timestart);
    }

    /**
     * This function will fail the currently running task, if there is one.
     */
    public static function fail_running_task(): void {
        self::get_manager_class()::fail_running_task();
    }

    /**
     * This function indicates that an adhoc task was not completed successfully and should be retried.
     *
     * @param \core\task\adhoc_task $task
     */
    public static function adhoc_task_failed(adhoc_task $task) {
        self::get_manager_class()::adhoc_task_failed($task);
    }

    /**
     * Records that a adhoc task is starting to run.
     *
     * @param adhoc_task $task Task that is starting
     * @param int $time Start time (leave blank for now)
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function adhoc_task_starting(adhoc_task $task, int $time = 0) {
        self::get_manager_class()::adhoc_task_starting($task, $time);
    }

    /**
     * This function indicates that an adhoc task was completed successfully.
     *
     * @param \core\task\adhoc_task $task
     */
    public static function adhoc_task_complete(adhoc_task $task) {
        self::get_manager_class()::adhoc_task_complete($task);
    }

    /**
     * This function indicates that a scheduled task was not completed successfully and should be retried.
     *
     * @param \core\task\scheduled_task $task
     */
    public static function scheduled_task_failed(scheduled_task $task) {
        self::get_manager_class()::scheduled_task_failed($task);
    }

    /**
     * Clears the fail delay for the given task and updates its next run time based on the schedule.
     *
     * @param scheduled_task $task Task to reset
     * @throws \dml_exception If there is a database error
     */
    public static function clear_fail_delay(scheduled_task $task) {
        self::get_manager_class()::clear_fail_delay($task);
    }

    /**
     * Records that a scheduled task is starting to run.
     *
     * @param scheduled_task $task Task that is starting
     * @param int $time Start time (0 = current)
     * @throws \dml_exception If the task doesn't exist
     */
    public static function scheduled_task_starting(scheduled_task $task, int $time = 0) {
        self::get_manager_class()::scheduled_task_starting($task, $time);
    }

    /**
     * This function indicates that a scheduled task was completed successfully and should be rescheduled.
     *
     * @param \core\task\scheduled_task $task
     */
    public static function scheduled_task_complete(scheduled_task $task) {
        self::get_manager_class()::scheduled_task_complete($task);
    }

    /**
     * Gets a list of currently-running tasks.
     *
     * @param  string $sort Sorting method
     * @return array Array of scheduled and adhoc tasks
     * @throws \dml_exception
     */
    public static function get_running_tasks($sort = ''): array {
        return self::get_manager_class()::get_running_tasks($sort);
    }

    /**
     * Cleanup stale task metadata.
     */
    public static function cleanup_metadata() {
        self::get_manager_class()::cleanup_metadata();
    }

    /**
     * This function is used to indicate that any long running cron processes should exit at the
     * next opportunity and restart. This is because something (e.g. DB changes) has changed and
     * the static caches may be stale.
     */
    public static function clear_static_caches() {
        self::get_manager_class()::clear_static_caches();
    }

    /**
     * Return true if the static caches have been cleared since $starttime.
     * @param int $starttime The time this process started.
     * @return boolean True if static caches need resetting.
     */
    public static function static_caches_cleared_since($starttime) {
        return self::get_manager_class()::static_caches_cleared_since($starttime);
    }

    /**
     * Gets class name for use in database table. Always begins with a \.
     *
     * @param string|task_base $taskorstring Task object or a string
     */
    public static function get_canonical_class_name($taskorstring) {
        return self::get_manager_class()::get_canonical_class_name($taskorstring);
    }

    /**
     * Returns if Moodle have access to PHP CLI binary or not.
     *
     * @return bool
     */
    public static function is_runnable(): bool {
        return self::get_manager_class()::is_runnable();
    }

    /**
     * Executes a cron from web invocation using PHP CLI.
     *
     * @param scheduled_task $task Task that be executed via CLI.
     * @return bool
     * @throws \moodle_exception
     */
    public static function run_from_cli(scheduled_task $task): bool {
        return self::get_manager_class()::run_from_cli($task);
    }

    /**
     * This behaves similar to passthru but filters every line via
     * the mtrace function so it can be post processed.
     *
     * @param string $command to run
     * @return void
     */
    public static function passthru_via_mtrace(string $command) {
        self::get_manager_class()::passthru_via_mtrace($command);
    }

    /**
     * Executes an ad hoc task from web invocation using PHP CLI.
     *
     * @param int   $taskid Task to execute via CLI.
     * @throws \moodle_exception
     */
    public static function run_adhoc_from_cli(int $taskid) {
        self::get_manager_class()::run_adhoc_from_cli($taskid);
    }

    /**
     * Executes ad hoc tasks from web invocation using PHP CLI.
     *
     * @param bool|null   $failedonly
     * @param string|null $classname  Task class to execute via CLI.
     * @throws \moodle_exception
     */
    public static function run_all_adhoc_from_cli(?bool $failedonly = false, ?string $classname = null) {
        self::get_manager_class()::run_all_adhoc_from_cli($failedonly, $classname);
    }

    /**
     * This checks whether or not there is a value set in config
     * for a scheduled task.
     *
     * @param string $classname Scheduled task's classname
     * @return bool true if there is an entry in config
     */
    public static function scheduled_task_has_override(string $classname): bool {
        return self::get_manager_class()::scheduled_task_has_override($classname);
    }
    /**
     * Get the key within the scheduled tasks config object that
     * for a classname.
     *
     * @param string $classname the scheduled task classname to find
     * @return string the key if found, otherwise null
     */
    public static function scheduled_task_get_override_key(string $classname): ?string {
        return self::get_manager_class()::scheduled_task_get_override_key($classname);
    }

    /**
     * Clean up failed adhoc tasks.
     */
    public static function clean_failed_adhoc_tasks(): void {
        self::get_manager_class()::clean_failed_adhoc_tasks();
    }
}
