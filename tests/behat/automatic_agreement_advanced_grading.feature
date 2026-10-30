@mod @mod_coursework @mod_coursework_automatic_agreement
Feature: Automatic agreement should work where an initial advanced grading strategy is used and then simple grading is the final agreement stage.

  # Notes - This tests rubrics specifically, but marking guide should work exactly the same way.
  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework        |
      | course                     | C1                |
      | name                       | CW1               |
      | numberofmarkers            | 2                 |
      | automaticagreementstrategy | average_grade     |
      | advancedgradingmethod_submissions | rubric     |
    And the following "activity" exists:
      | activity                   | coursework        |
      | course                     | C1                |
      | name                       | CW2               |
      | numberofmarkers            | 2                 |
      | automaticagreementstrategy | average_grade     |
      | advancedgradingmethod_submissions | rubric     |
      | finalstagegrading          | 1                 |
    And the following "activity" exists:
      | activity                   | coursework        |
      | course                     | C1                |
      | name                       | CW3               |
      | numberofmarkers            | 2                 |
      | automaticagreementstrategy | percentage_distance |
      | advancedgradingmethod_submissions | rubric     |
      | finalstagegrading          | 1                 |
      | automaticagreementrange    | 20                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework        | finalisedstatus |
      | student1    | CW1               | 1               |
      | student1    | CW2               | 1               |
      | student1    | CW3               | 1               |
    And I am on the "CW1" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Rubric"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name        | Test rubric        |
      | Description | Rubric description |
    And I define the following rubric:
      | C1 | Bad | 1 | Ok | 2 | Good | 3 |
      | C2 | Bad | 1 | Ok | 2 | Good | 3 |
    And I press "Save rubric and make it ready"
    And I am on the "CW2" "coursework activity" page
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Rubric"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name        | Test rubric        |
      | Description | Rubric description |
    And I define the following rubric:
      | C1 | Bad | 1 | Ok | 2 | Good | 3 |
      | C2 | Bad | 1 | Ok | 2 | Good | 3 |
    And I press "Save rubric and make it ready"
    And I am on the "CW3" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Rubric"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name        | Test rubric        |
      | Description | Rubric description |
    And I define the following rubric:
      | C1 | Bad | 1 | Ok | 2 | Good | 3 |
      | C2 | Bad | 1 | Ok | 2 | Good | 3 |
    And I press "Save rubric and make it ready"

  # An advanced method throughout the whole process, should not be auto calculated.
  @javascript
  Scenario: Automatic grade calculation should not happen if advanced grading method is used throughout.
    Given I am on the "CW1" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | C1 | 3 | Criterion comment |
      | C2 | 2 | Criterion comment |
    And I press "Save and finalise"
    And I am on the "CW1" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | C1 | 1 | Criterion comment |
      | C2 | 2 | Criterion comment |
    And I press "Save and finalise"
    When I am on the "CW1" "coursework activity" page logged in as "manager1"
    Then I should not see "Automatically agreed"

  @javascript
  Scenario: Automatic average grade calculation, when final stage uses a different method.
    Given I am on the "CW2" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | C1 | 3 | Criterion comment |
      | C2 | 2 | Criterion comment |
    And I press "Save and finalise"
    And I am on the "CW2" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | C1 | 1 | Criterion comment |
      | C2 | 2 | Criterion comment |
    When I press "Save and finalise"
    Then I should see "Automatically agreed" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "66.67" in the "[data-behat-markstage='final_agreed'] [data-mark]" "css_element"

  @javascript
  Scenario: Automatic percentage distance grade calculation, when final stage uses a different method.
    Given I am on the "CW3" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | C1 | 3 | Criterion comment |
      | C2 | 2 | Criterion comment |
    And I press "Save and finalise"
    And I am on the "CW3" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | C1 | 3 | Criterion comment |
      | C2 | 1 | Criterion comment |
    When I press "Save and finalise"
    Then I should see "Automatically agreed" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "83.33" in the "[data-behat-markstage='final_agreed'] [data-mark]" "css_element"
