@mod @mod_coursework
Feature: warnings when settings are not right

  As a manager
  I want to know when there are issues with the setup of the coursework instance
  So that I can take corrective action before stuff goes wrong

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 3          |

  Scenario: managers see a warning about there being too few teachers
    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    Then I should see "There are only 0 teachers enrolled on the course, but the settings for this coursework require 3."

    When the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |

    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    Then I should see "There are only 2 teachers enrolled on the course, but the settings for this coursework require 3."

    When the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher3 | teacher   | teacher3 | teacher3@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher3 | C1     | teacher |

    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    Then I should not see "but the settings for this coursework require 3."
