@mod @mod_coursework @mod_coursework_moderation_view
Feature: View moderation feedback

  Scenario: As an assessor I’ve received some moderation and when I view this I should see the moderator's feedback with no error
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
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    Then I should see "Moderation" in the table row containing "John1"
    And I should see "Agreed" in the table row containing "John1"
    And I should not see "Disagreed" in the table row containing "John1"
