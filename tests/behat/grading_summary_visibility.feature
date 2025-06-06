@mod @mod_coursework

Feature: Grading summary table visibility
  Grading summary shouldn't be displayed to students

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And there is a teacher

  Scenario: Grading summary table is visible to teachers
    Given I log in as the teacher
    And I visit the coursework page
    Then I should see "Marking summary"
    And I should see "Marked and published"

  Scenario: Grading summary table is not visible to students
    Given I log in as the student
    And I visit the coursework page
    Then I should not see "Marking summary"
    And I should not see "Marked and published"
