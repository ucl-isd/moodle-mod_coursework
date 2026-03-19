@mod @mod_coursework @mod_coursework_marking_guide_agreement_frequent_comments
Feature: Marking guide with frequent comments
  Users can make use of the marking guide to submit grades for final approval based on frequently used comments.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework    |
      | course          | C1            |
      | name            | Coursework    |
      | numberofmarkers | 2             |
      | deadline        | ##yesterday## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

    And I am on the "Coursework" "coursework activity" page logged in as "admin"

    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Marking guide"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name | Behat marking form |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers | Maximum score |
      | A criteria     | Description for students | Description for markers | 100           |
    And I define the following frequently used comments:
      | Comment 1          |
      | Comment 2          |
      | Frequent Comment 3 |
    And I press "Save marking guide and make it ready"

    And I am on the "Coursework" "coursework activity" page

    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I grade by filling the marking guide with:
      | A criteria | 6 |  |

    And I click on "Insert frequently used comment" "button" in the "A criteria" "table_row"
    And I wait "1" seconds
    And I press "Frequent Comment 3"
    And I wait "1" seconds
    Then the field "A criteria criterion remark" matches value "Frequent Comment 3"
    And I press "Save and finalise"

    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I grade by filling the marking guide with:
      | A criteria | 8 | Grader two really likes it |
    And I press "Save and finalise"

  @javascript
  Scenario: Final marker reviews assessor feedback and uses frequent comments.
    Given I am on the "Coursework" "coursework activity" page
    And I follow "Agree marking"

    And I should see "A criteria"
    And I should see "6" in the "A criteria" "table_row"
    And I should see "8" in the "A criteria" "table_row"
    And I should see "Frequent Comment 3" in the "Feedback" "table_row"
    And I should see "Grader two really likes it" in the "Feedback" "table_row"

    And I click on "Insert frequently used comment" "button" in the "A criteria" "table_row"
    And I wait "1" seconds
    And I press "Comment 2"
    And I wait "1" seconds

    Then the field "A criteria criterion remark" matches value "Comment 2"
    And I set the field "Mark (0–100)" to "10"
    And I press "Save and finalise"

    Then I should see "Ready for release" in the "student1" "table_row"
    And I follow "Release the marks"
    And I press "Confirm"
    And I log out

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Comment 2" in the ".coursework-feedback .behat-criterion-remark" "css_element"

  @javascript
  Scenario: Pressing return key while focussed on a mark input field does not launch frequent comments modal.
    Given I visit the coursework page
    And I click on the edit feedback button for assessor 2
    And I set the field "Mark (0–100)" to "8"
    And I press the enter key
    And I wait until the page is ready
    # Frequent comments modal not launched.
    And I should not see "Frequent Comment 3"
