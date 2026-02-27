@mod @mod_coursework
Feature: Installing the coursework module and making sure it works

    In order to start using the Coursework module
    As an admin
    I need to be able to successfully install the module in a course and add an instance

  Scenario: I can add a new instance of the coursework module to a course
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
      | enablecompletion  | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher2 | teacher   | teacher2        | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher2 | C1     | editingteacher |
    And I log in as "teacher2"
    When I visit the course page
    And I turn editing mode on
    When I add a "coursework" activity to course "C1" section "3" and I fill the form with:
            | name        | Test coursework             |
            | Description | Test coursework description |
    Then I should be on the course page
