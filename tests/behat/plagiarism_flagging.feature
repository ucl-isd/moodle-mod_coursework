@mod @mod_coursework @javascript
Feature: Teachers and course administrators should be able to add and edit
  plagiarism flags

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "plagiarismflagenabled" setting is "1" in the database
    And there is a student
    And there is a teacher
    And the student has a submission
    And the submission is finalised

  Scenario: Teacher can flag a submission for plagiarism
    Given I am logged in as a teacher
    And I visit the coursework page
    And I should not see "Flagged for plagiarism"
    And I click on "Actions" "button"
    And I click on "Plagiarism action" "link"
    And I set the field "Status" to "Under Investigation"
    And I set the field "Internal comment" to "Test comment"
    And I click on "Save" "button"
    Then I should see "Flagged for plagiarism"
