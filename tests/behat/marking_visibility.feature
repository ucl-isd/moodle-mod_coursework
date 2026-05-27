@mod @mod_coursework

Feature: Submissions table and marking summary table should be visible to
      teachers and external examiners, but not visible to students

  Scenario: Visibility of main submissions table and marking summary table
    Given the following "roles" exist:
      | name              | shortname        | archetype |
      | External examiner | externalexaminer |           |
    And the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the following "users" exist:
      | username  | firstname | lastname | email                |
      | teacher1  | teacher   | teacher1 | teacher1@example.com |
      | student1  | student   | student1 | student1@example.com |
      | examiner1 | External  | Examiner | external@example.com |
    Given the following "role capability" exists:
      | role                                   | externalexaminer |
      | mod/coursework:view                    | allow            |
      | mod/coursework:viewallgradesatalltimes | allow            |
      | mod/coursework:viewextensions          | allow            |
    And the following "course enrolments" exist:
      | user      | course | role             |
      | student1  | C1     | student          |
      | teacher1  | C1     | editingteacher   |
      | examiner1 | C1     | externalexaminer |

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Marking summary"
    And "Submissions table" "table" should not exist

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "Marking summary"
    And "Submissions table" "table" should exist

    When I am on the "Coursework" "coursework activity" page logged in as "examiner1"
    Then I should see "Marking summary"
    And "Submissions table" "table" should exist
