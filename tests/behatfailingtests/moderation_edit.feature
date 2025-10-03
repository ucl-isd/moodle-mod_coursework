@mod @mod_coursework @javascript
Feature: Moderation of feedback

  Scenario: When I moderate feedback then edit the moderation by clicking the pencil button the moderation form should load with no error
    Given there is a course
    And there is a coursework
    And there is a student
    And there is a teacher
    And the coursework "numberofmarkers" setting is "1" in the database
    And the coursework "moderationagreementenabled" setting is "1" in the database
    And the student has a submission
    And there is feedback for the submission from the teacher
    And I log in as "admin"
    And I visit the coursework page
    And I click on "Moderate" "link"
    And I click on "Save changes" "button"
    When I click the edit moderation agreement link
    Then I should see "Moderation agreement"
