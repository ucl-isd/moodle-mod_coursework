@mod @mod_coursework @javascript
Feature: View moderation feedback

  Scenario: As an assessor I’ve received some moderation and when I view this I should see the moderator's feedback with no error
    Given there is a course
    And there is a coursework
    And there is a student
    And there is a teacher
    And the following "permission overrides" exist:
      | capability                             | permission | role    | contextlevel | reference |
      | mod/coursework:viewallgradesatalltimes | Allow      | teacher | Course       | C1        |
    And the coursework "numberofmarkers" setting is "1" in the database
    And the coursework "moderationagreementenabled" setting is "1" in the database
    And the student has a submission
    And there is feedback for the submission from the teacher
    And I log in as "admin"
    And I visit the coursework page
    And I click on "Moderate" "link"
    And I click on "Save changes" "button"
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    When I click on "View moderation" "link"
    Then I should see "Moderation for student"
