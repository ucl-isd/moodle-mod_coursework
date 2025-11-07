@mod @mod_coursework

Feature: Grading summary table visibility
  Grading summary shouldn't be displayed to students

  Scenario: Grading summary table is not visible to students
    Given there is a course
    And there is a coursework
    And there is a student
    And there is a teacher
    And I log in as the student
    And I visit the coursework page
    Then I should not see "Marking summary"
    And I should not see "Marks released"
