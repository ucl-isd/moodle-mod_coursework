@mod @mod_coursework @mod_coursework_moderation_edit
Feature: Moderation of feedback

  Scenario: When I moderate feedback then edit the moderation by clicking the pencil button the moderation form should load with no error
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers   | 1          |
    And there is a student called "John1"
    And there is a teacher
    And the coursework "moderationagreementenabled" setting is "1" in the database
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there is finalised feedback for the submission from the teacher
    And I log in as "admin"
    And I am on the "Coursework" "coursework activity" page
    And I click on "Agree marking" "link"
    And I set the field "Moderation agreement" to "Agreed"
    And I click on "Save changes" "button"
    Then I should see "Moderation"
    And I should see "Agreed" in the table row containing "John1"
    And I should not see "Disagreed" in the table row containing "John1"
