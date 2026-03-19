@mod @mod_coursework @javascript @mod_coursework_plagiarism_flagging_group
Feature: Teachers should be able to add and edit plagiarism flags for group submissions

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the coursework "plagiarismflagenabled" setting is "1" in the database
    And the coursework "usegroups" setting is "1" in the database
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "groups" exist:
      | course | idnumber | name     |
      | C1     | G1       | My group |
    And the following "group members" exist:
      | group | user     |
      | G1    | student1 |
    And there is a teacher
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus | createdby |
      | G1          | Coursework | 1               | student1  |

  Scenario: Teacher can flag a group submission for plagiarism
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I should not see "Flagged for plagiarism"
    And I click on "Actions" "button"
    And I click on "Plagiarism action" "link"
    And I set the field "Status" to "Under Investigation"
    And I set the field "Internal comment" to "Test comment"
    And I click on "Save" "button"
    And I wait until the page is ready
    Then I should see "Flagged for plagiarism"
