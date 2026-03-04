@mod @mod_coursework @mod_coursework_moderation_view
Feature: View moderation feedback

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | Coursework |
      | numberofmarkers            | 1          |
      | moderationagreementenabled | 1          |
    And the following "users" exist:
      | username   | firstname | lastname | email                  |
      | moderator1 | moderator | 1        | moderator1@example.com |
      | teacher1   | teacher   | teacher1 | teacher1@example.com   |
      | student1   | John1     | student1 | student1@example.com   |
    And the following "role" exists:
      | shortname               | moderator |
      | name                    | moderator |
      | context_course          | 1         |
      | mod/coursework:moderate | allow     |
      | mod/coursework:view     | allow     |
    And the following "course enrolments" exist:
      | user       | course | role      |
      | moderator1 | C1     | moderator |
      | student1   | C1     | student   |
      | teacher1   | C1     | teacher   |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 58    | Blah            | 1         |

  Scenario: As an assessor I’ve received some moderation and when I view this I should see the moderator's feedback with no error
    Given I am on the "Coursework" "coursework activity" page logged in as "moderator1"
    Then I should see "58" in the table row containing "John1"
    When I click on "58" "link"
    Then I should see "Blah"

    When I am on the "Coursework" "coursework activity" page
    And I click on "Agree marking" "link"
    Then I should see "teacher teacher1" in the "data-behat-markstage=\"assessor_1\"" "css_element"
    And I should see "58" in the "data-behat-markstage=\"assessor_1\"" "css_element"
    And I should see "Blah" in the "data-behat-markstage=\"assessor_1\"" "css_element"

    When I set the field "Moderation agreement" to "Agreed"
    #And I click on "Save changes" "button"
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "Moderation" in the table row containing "John1"
    And I should see "Agreed" in the table row containing "John1"
    And I should not see "Disagreed" in the table row containing "John1"
