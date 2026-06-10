@mod @mod_coursework
Feature: Downloading the marking spreadsheet

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
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: Student should not be able to download the marking spreadsheet
    Given I am logged in as "student1"

    # Confirm student is not on the Coursework activity page.
    And I should not see "Submitted"

    When I am on the "Coursework" "mod_coursework > Download final marks" page

    # User without appropriate capability should just see the Coursework activity page.
    Then I should see "Submitted" in the ".behat-submission-information" "css_element"
