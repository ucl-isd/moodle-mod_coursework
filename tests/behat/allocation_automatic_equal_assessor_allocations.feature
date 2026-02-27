@mod @mod_coursework @mod_coursework_markingallocation
Feature: Automatic equal assessor allocations

  As a manager
  I want to be able to allocate assesors to students
  So that the marking is fairly distributed and the interface is less cluttered for teachers,
  and they don't mark to many or too few.

  Background:
    Given the following "permission overrides" exist:
      | capability                      | permission | role    | contextlevel | reference |
      | mod/coursework:addinitialgrade | Prevent      | manager | Course       | C1        |
    And the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity          | coursework |
      | course            | C1         |
      | name              | Coursework |
      | allocationenabled | 1          |
      | numberofmarkers   | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
      | student1 | student   | student1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |

  Scenario: Automatic allocations should work
    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "student student1" in the ".mod-coursework-submissions-table" "css_element"
