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
 * Undo button AJAX modules.
 *
 * @module     core/question/replay
 * @package    core_question/replay
 * @copyright  2016 Royal Australasian College of Surgeons
 * @author     Darren Cocco
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/str', 'core/notification'],
    function($, ajax, templates, str, notification) {
    return {
        /**
         * Initialises each undo button.
         *
         * @param {string} button - field name of the button
         * @param {integer} questionAttempt - ID of the question attempt
         * @param {string} replayField - field name to put the replay sequence number
         */
        init: function(button, questionAttempt, replayField) {
            // Fetch title of modal dialogue
            str.get_strings([
                {
                    key: 'replaydialoguetitle',
                    component: 'core_question'
                },
                {
                    key: 'warning',
                    component: 'moodle'
                },
                {
                    key: 'replaydialoguewarningmessage',
                    component: 'core_question'
                },
                {
                    key: 'continue',
                    component: 'moodle'
                },
                {
                    key: 'nostepsavailablemessage',
                    component: 'core_question'
                },
                {
                    key: 'info',
                    component: 'moodle'
                }
            ]).done($.proxy(function(response) {
                this.dialogueTitle = response[0];
                this.warningTitle = response[1];
                this.warningMessage = response[2];
                this.continue = response[3];
                this.noStepsAvailableMessage = response[4];
                this.info = response[5];
            }, this));

            // Attach dialogue opening to specified button
            $('#' + this.escapeCss(button)).click($.proxy(function() {
                this.replayResponseDialogue(questionAttempt, replayField);
            }, this));
        },

        /**
         * Retrieves steps sequence numbers including what time they were created.
         *
         * @param {integer} questionAttempt - ID of question attempt
         * @returns {Promise}
         */
        getQuestionAttemptSteps: function(questionAttempt) {
            return ajax.call([{
                methodname: 'core_question_get_steps',
                args: {
                    questionattempt: questionAttempt
                }
            }])[0];
        },

        /**
         * Loads a replay dialogue for the question attempt.
         *
         * @param {integer} questionAttempt - ID of question attempt
         * @param {string} replayField - name of form field for replays
         */
        replayResponseDialogue: function(questionAttempt, replayField) {
            var stepPromise = this.getQuestionAttemptSteps(questionAttempt);
            stepPromise.done($.proxy(function(response) {
                var currentTime = new Date().getTime() / 1000;
                // Process the steps
                var steps = $.map(response.steps, function(step) {
                    // Remove the initial set up step and any auto-save steps
                    if (step.stepnumber < 1) {
                        return null;
                    }
                    // Add an elapsed minutes from current time value to provide an easier to understand interface.
                    step.elapsedMinutes = Math.floor((currentTime - step.timecreated) / 60);
                    return step;
                });
                steps.sort(function(a, b) {
                    if (a.stepnumber > b.stepnumber) {
                        return -1;
                    }
                    if (a.stepnumber < b.stepnumber) {
                        return 1;
                    }
                    return 0;
                });
                if (steps.length === 0) {
                    notification.alert(this.warningTitle, this.noStepsAvailableMessage, this.continue);
                    return;
                }
                var context = {
                    replayField: replayField,
                    steps: steps
                };
                templates.render('core_question/replayresponse_dialoguecontent', context).done($.proxy(function(html) {
                    var dialogue = new M.core.dialogue({
                        draggable: true,
                        modal: true,
                        closeButton: true,
                        headerContent: this.dialogueTitle,
                        bodyContent: html
                    });
                    $('#' + dialogue.get("id")).on('click', '[data-action="replayStep"]',
                        $.proxy(this.replayResponse, this));
                    dialogue.show();
                    // FIXME: Should probably clean up the modal dialogue when they click cancel
                }, this));
            }, this));
        },

        /**
         * Modifies replay field according to user selected
         * sequence number.
         *
         * @param {event} event - event triggered by user when they have selected and confirmed the replay
         */
        replayResponse: function(event) {
            var data = $(event.target).data();
            var replayField = $('[name="' + data.replayfield + '"]');
            var newSequence = ($("#" + this.escapeCss(data.stepfield))).val();
            if (newSequence === null || newSequence === undefined || newSequence === "") {
                notification.alert(this.warningTitle, this.warningMessage, this.continue);
                return;
            } else {
                replayField.val(newSequence);
            }
            M.core_formchangechecker.set_form_submitted();
            replayField[0].form.submit();
        },

        /**
         * Escapes question engine field names as they frequently
         * have CSS control symbols in them.
         *
         * @param {string} string - unescaped question field name
         * @returns {string} = escaped question field name
         */
        escapeCss: function(string) {
            return string.replace(/(:|\.|\[|\]|,|=)/g, "\\$1");
        }
    };
});