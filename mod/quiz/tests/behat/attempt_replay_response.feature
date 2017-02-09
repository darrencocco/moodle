@mod @mod_quiz
Feature: Attempt a quiz with two essay questions
  As a student
  I make a mistake on a question
  I need to be able to undo it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | responsereplayenabled |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    | 1                     |
    And the following config values are set as admin:
      | responsereplayavailable | 1 | quiz |
    And the following "questions" exist:
      | questioncategory | qtype | name | questiontext    |
      | Test questions   | essay | E1   | First question  |
      | Test questions   | essay | E2   | Second question |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | E1       | 1    |         |
      | E2       | 1    | 3.0     |

  @javascript
  Scenario: I attempt an essay question and try to replay a response without any saves
    When I log in as "student"
    And I follow "Course 1"
    And I follow "Quiz 1"
    And I press "Attempt quiz now"
    And I wait to be redirected
    And I press "q147000:1_-replay"
    Then I should see "There are no previous saved versions available to undo to"

  @javascript
  Scenario: I attempt an essay question and delete then restore my response
    When I log in as "student"
    And I follow "Course 1"
    And I follow "Quiz 1"
    And I press "Attempt quiz now"
    And I set the answer of "essay" question "1" to "field one answer one"
    And I set the answer of "essay" question "2" to "field two answer one"
    And I press "Finish attempt ..."
    And I press "Return to attempt"
    And I set the answer of "essay" question "1" to "field one answer two"
    And I press "Finish attempt ..."
    And I press "Return to attempt"
    And I set the answer of "essay" question "1" to "field one answer three"
    And I press "q147000:1_-replay"
    Then I should see "Available saves"
    And the "Available saves" select box should contain "1"
    And the "Available saves" select box should contain "2"
    When I select "2" from the "Available saves" singleselect
    And I click on "Undo" "button" in the "Undo" "dialogue"
    Then the answer of "essay" question "1" matches "field one answer two"
    And the sequence check of question "1" matches "5"