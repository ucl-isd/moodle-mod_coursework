@mod @mod_coursework

Feature: When a coursework uses double marking the marking summary table should display the expected values

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 2          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |

  Scenario: Manager and Teacher's view when there are no submissions
    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "0/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "0/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Manager and Teacher's view when there is a submission
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Manager and Teacher's view when submission is marked once
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment |
      | student1    | Coursework | teacher1 | assessor_1      | 58    | Blah            |

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Manager's view when submission is marked twice
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "1" in the "Ready for agreement" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Manager's view when submission has final mark
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
      | student1    | Coursework | manager1 | final_agreed_1  | 45    | blah             | 1         |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "1" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"
