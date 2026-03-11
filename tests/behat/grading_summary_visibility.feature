@mod @mod_coursework

Feature: Grading summary table visibility
  Grading summary shouldn't be displayed to students

  Scenario: Grading summary table is not visible to students
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And I log in as the student
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Marking summary"
    And I should not see "Marks released"
