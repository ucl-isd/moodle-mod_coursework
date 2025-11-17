@mod @mod_coursework @javascript
Feature: Teachers should be able to add and edit plagiarism flags for group submissions

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "plagiarismflagenabled" setting is "1" in the database
    And the coursework "usegroups" setting is "1" in the database
    And there is a student
    And the student is a member of a group
    And there is a teacher
    And the group has a submission
    And the submission is finalised

  Scenario: Teacher can flag a group submission for plagiarism
    Given I am logged in as a teacher
    And I visit the coursework page
    And I should not see "Flagged for plagiarism"
    And I click on "Actions" "button"
    And I click on "Plagiarism action" "link"
    And I set the field "Status" to "Under Investigation"
    And I set the field "Internal comment" to "Test comment"
    And I click on "Save" "button"
    Then I should see "Flagged for plagiarism"
