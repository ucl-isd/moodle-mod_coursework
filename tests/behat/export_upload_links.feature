@mod @mod_coursework  @mod_coursework_export_upload_links
Feature: Download and upload buttons on submissions page
  These should only appear when there are submissions
  They should contain the expected menu items corresponding the user's role

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: When there are no submissions the teacher should not see an upload or download menu
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "Download"
    And I should not see "Upload"

    When the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "Download"
    And I should see "Upload"

    When I click on "Upload" "button"
    Then I should see "Marking spreadsheet"
    And I should see "Feedback files in a zip"

    When I click on "Download" "button"
    And I wait until the page is ready
    Then I should see "Submitted files"
    And I should see "Marking spreadsheet"
    But I should not see "Final marks"
