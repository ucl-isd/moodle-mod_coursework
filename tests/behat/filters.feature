@mod @mod_coursework @mod_coursework_filters @javascript
Feature: Filtering the submissions table.
  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | CW1        |
      | numberofmarkers            | 1          |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | CW2        |
      | numberofmarkers            | 2          |
    And the following "users" exist:
      | username   | firstname | lastname    | email                  |
      | teacher1   | teacher   | teacher1    | teacher1@example.com   |
      | teacher2   | teacher   | teacher2    | teacher2@example.com   |
      | student1   | Andy      | Andrewson   | student1@example.com   |
      | student2   | Brian     | Brianson    | student2@example.com   |
      | student3   | Chris     | Christenson | student3@example.com   |
      | student4   | Dave      | Davidson    | student4@example.com   |
    And the following "course enrolments" exist:
      | user       | course | role      |
      | student1   | C1     | student   |
      | student2   | C1     | student   |
      | student3   | C1     | student   |
      | student4   | C1     | student   |
      | teacher1   | C1     | teacher   |
      | teacher2   | C1     | teacher   |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | CW1        | 1               |
      | student2    | CW1        | 1               |
      | student3    | CW1        | 1               |
      | student4    | CW1        | 1               |
      | student1    | CW2        | 1               |
      | student2    | CW2        | 1               |
      | student3    | CW2        | 1               |
      | student4    | CW2        | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment | finalised |
      | student1    | CW1        | teacher1 | assessor_1      | 0     | Blah            | 1         |
      | student2    | CW1        | teacher1 | assessor_1      | 42    | Blah            | 1         |
      | student3    | CW1        | teacher1 | assessor_1      | 44    | Blah            | 1         |
      | student4    | CW1        | teacher1 | assessor_1      | 87    | Blah            | 1         |
      | student1    | CW2        | teacher1 | assessor_1      | 1     | Blah            | 1         |
      | student2    | CW2        | teacher1 | assessor_1      | 2     | Blah            | 1         |
      | student3    | CW2        | teacher1 | assessor_1      | 3     | Blah            | 1         |
      | student4    | CW2        | teacher1 | assessor_1      | 4     | Blah            | 1         |
      | student1    | CW2        | teacher2 | assessor_2      | 99    | Blah            | 1         |
      | student2    | CW2        | teacher2 | assessor_2      | 98    | Blah            | 1         |
      | student3    | CW2        | teacher2 | assessor_2      | 97    | Blah            | 1         |
      | student4    | CW2        | teacher2 | assessor_2      | 96    | Blah            | 1         |
      | student1    | CW2        | teacher1 | final_agreed_1  | 10    | Blah            | 1         |
      | student2    | CW2        | teacher1 | final_agreed_1  | 20    | Blah            | 1         |
      | student3    | CW2        | teacher1 | final_agreed_1  | 30    | Blah            | 1         |
      | student4    | CW2        | teacher1 | final_agreed_1  | 40    | Blah            | 1         |

  Scenario: Filtering the submissions table when there is only one marker should apply based on the assessor's mark.
    Given I am logged in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.99
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I click on "Save changes" "button"
    And I am on the "CW1" "coursework activity" page logged in as "teacher1"
    And I click on "Filter submissions" "button"
    And I click on "0 - 0.99%" "checkbox"
    Then I should see "Andy Andrewson"
    And I should not see "Brian Brianson"
    And I should not see "Chris Christenson"
    And I should not see "Dave Davidson"
    And I click on "Filter submissions" "button"
    And I click on "Reset filters" "button"
    And I click on "Filter submissions" "button"
    And I click on "40 - 49.99%" "checkbox"
    Then I should not see "Andy Andrewson"
    And I should see "Brian Brianson"
    And I should see "Chris Christenson"
    And I should not see "Dave Davidson"

  Scenario: Filtering with multiple markers should apply based on the final agreed mark.
    Given I am logged in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.99
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I click on "Save changes" "button"
    And I am on the "CW2" "coursework activity" page logged in as "teacher1"
    And I click on "Filter submissions" "button"
    And I click on "0 - 0.99%" "checkbox"
    Then I should not see "Andy Andrewson"
    And I should not see "Brian Brianson"
    And I should not see "Chris Christenson"
    And I should not see "Dave Davidson"
    And I click on "Filter submissions" "button"
    And I click on "Reset filters" "button"
    And I click on "Filter submissions" "button"
    And I click on "1 - 39.99%" "checkbox"
    Then I should see "Andy Andrewson"
    And I should see "Brian Brianson"
    And I should see "Chris Christenson"
    And I should not see "Dave Davidson"
    And I click on "Filter submissions" "button"
    And I click on "Reset filters" "button"
    And I click on "Filter submissions" "button"
    And I click on "40 - 49.99%" "checkbox"
    Then I should not see "Andy Andrewson"
    And I should not see "Brian Brianson"
    And I should not see "Chris Christenson"
    And I should see "Dave Davidson"
