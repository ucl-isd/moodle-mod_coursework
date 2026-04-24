@mod @mod_coursework @mod_coursework_plagiarism_turnitin_links
Feature: Check that Turnitin functionality is not visible when disabled.
#  These tests can be expected to fail if the plagiarism/turnitin plugin is not also installed in the build.

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
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  @javascript
  Scenario: Submission does *not* have Turnitin report on page load as settings are off
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    # Ensure the table is scrolled into view, since JS loading is not triggered for rows out of view.
    And I hover "table.mod-coursework-submissions-table" "css_element"
    And I wait until the page is ready
    And I should not see "[TURNITIN DUMMY LINKS HTML]"

  @javascript
  Scenario: Submission does *not* have Turnitin report on page load as global settings are on but course setting is still off
    Given the following config values are set as admin:
      | config                             | value | plugin              |
      | enableplagiarism                   | 1     |                     |
      | enabled                            | 1     | plagiarism_turnitin |
      | plagiarism_turnitin_mod_coursework | 1     | plagiarism_turnitin |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    # Ensure the table is scrolled into view, since JS loading is not triggered for rows out of view.
    And I hover "table.mod-coursework-submissions-table" "css_element"
    And I wait until the page is ready
    # The course specific setting is still off at this point so I do not see the links.
    And I should not see "[TURNITIN DUMMY LINKS HTML]"
