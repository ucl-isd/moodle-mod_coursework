@mod @mod_coursework
Feature: Installing the coursework module and making sure it works

  In order to start using the Coursework module
  As an admin
  I need to be able to successfully install the module in a course and add an instance

  Scenario: I can add a new instance of the coursework module to a course
    Given the following "course" exists:
      | fullname         | Course 1 |
      | shortname        | C1       |
      | enablecompletion | 1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    And I turn editing mode on
    And I add a "coursework" activity to course "C1" section "0" and I fill the form with:
      | name        | Test coursework             |
      | Description | Test coursework description |
    And I should see "Test coursework" in the "General" "section"
