@mod @mod_coursework
# Although JavaScript isn't needed for the functionality being tested, it is
# needed to hide any modals (for example, New Extension).

Feature: When there is a deadline for submissions this should appear on the activity page

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework  |
      | course   | C1          |
      | name     | Coursework  |
      | deadline | ##+1 week## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |

  Scenario: A teacher and a student should both see the deadline
    Given I log in as a teacher
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see due date "##+1 week##%d %B %Y##"
    But I should not see "Extended deadline"

    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see due date "##+1 week##%d %B %Y##"
    But I should not see "Extended deadline"
