@mod @mod_coursework @mod_coursework_moderation_view
Feature: View moderation feedback

  Scenario: As an assessor I’ve received some moderation and when I view this I should see the moderator's feedback with no error
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
    And the following "users" exist:
      | username   | firstname | lastname | email                  |
      | moderator1 | moderator | 1        | moderator1@example.com |
    Given the following "role" exists:
      | shortname               | moderator |
      | name                    | moderator |
      | context_course          | 1         |
      | mod/coursework:moderate | allow     |
      | mod/coursework:view     | allow     |
    And the following "course enrolments" exist:
      | user       | course | role    |
      | moderator1 | C1     | moderator |
    And the coursework "moderationagreementenabled" setting is "1" in the database
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there is finalised feedback for the submission from the teacher
    When I log in as "moderator1"
    And I am on the "Coursework" "coursework activity" page
    Then I should see "58" in the table row containing "John1"
    When I click on "58" "link"
    Then I should see "Blah"
    When I am on the "Coursework" "coursework activity" page
    And I click on "Agree marking" "link"
    And I set the field "Moderation agreement" to "Agreed"
    And I click on "Save changes" "button"
    And I log out
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see "Moderation" in the table row containing "John1"
    And I should see "Agreed" in the table row containing "John1"
    And I should not see "Disagreed" in the table row containing "John1"
