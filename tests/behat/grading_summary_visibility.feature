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
    And there is a student
    And there is a teacher
    And I log in as the student
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Marking summary"
    And I should not see "Marks released"
