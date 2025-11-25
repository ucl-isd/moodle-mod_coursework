@mod @mod_coursework_moderation_edit
Feature: Moderation of feedback

  Scenario: When I moderate feedback then edit the moderation by clicking the pencil button the moderation form should load with no error
    Given there is a course
    And there is a coursework
    And there is a student called "John1"
    And there is a teacher
    And the coursework "numberofmarkers" setting is "1" in the database
    And the coursework "moderationagreementenabled" setting is "1" in the database
    And the student has a submission
    And the submission is finalised
    And there is finalised feedback for the submission from the teacher
    And I log in as "admin"
    And I visit the coursework page
    And I click on "Agree marking" "link"
    And I set the field "Moderation agreement" to "Agreed"
    And I click on "Save changes" "button"
    Then I should see "Moderation"
    And I should see "Agreed" in the table row containing "John1"
    And I should not see "Disagreed" in the table row containing "John1"
