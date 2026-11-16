@mod @mod_coursework @mod_coursework_copy_to_agreed
Feature: Copy a marker's criterion marks and feedback to the agreed mark
  In order to agree marks more quickly
  As a user agreeing marks with a marking guide or rubric
  I need to be able to copy an individual marker's criterion mark and feedback into the agreed marking form

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activities" exist:
      | activity   | course | name        | numberofmarkers | deadline      |
      | coursework | C1     | Guide CW    | 2               | ##yesterday## |
      | coursework | C1     | Rubric CW   | 2               | ##yesterday## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Guide CW   | 1               |
      | student1    | Rubric CW  | 1               |

  @javascript
  Scenario: Copy marker criterion marks and feedback to agreed when using a marking guide
    Given I am on the "Guide CW" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Marking guide"
    And I wait until the page is ready
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name | Behat marking guide |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers | Maximum score |
      | G1             | Description for students | Description for markers | 5             |
      | G2             | Description for students | Description for markers | 10            |
    And I press "Save marking guide and make it ready"
    And I am on the "Guide CW" "coursework activity" page
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I grade by filling the marking guide with:
      | G1 | 2 | Marker one G1 comment |
      | G2 | 8 | Marker one G2 comment |
    And I press "Save and finalise"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I grade by filling the marking guide with:
      | G1 | 1 | Marker two G1 comment |
      | G2 | 4 | Marker two G2 comment |
    And I press "Save and finalise"
    When I follow "Agree marking"
    Then the field "G1 criterion remark" matches value ""
    And the field "Mark (0–5)" matches value ""
    And the field "G2 criterion remark" matches value ""
    And the field "Mark (0–10)" matches value ""
    # Copy marker 2 for G1.
    When I click on "button[aria-label='Copy Marker 2 mark and feedback for G1 to agreed']" "css_element"
    Then the field "G1 criterion remark" matches value "Marker two G1 comment"
    And the field "Mark (0–5)" matches value "1"
    # Other criteria are not affected.
    And the field "G2 criterion remark" matches value ""
    And the field "Mark (0–10)" matches value ""
    # Copying another marker replaces what is there.
    When I click on "button[aria-label='Copy Marker 1 mark and feedback for G1 to agreed']" "css_element"
    Then the field "G1 criterion remark" matches value "Marker one G1 comment"
    And the field "Mark (0–5)" matches value "2"
    # Copy marker 1 for G2, then edit the copied values before saving.
    When I click on "button[aria-label='Copy Marker 1 mark and feedback for G2 to agreed']" "css_element"
    Then the field "G2 criterion remark" matches value "Marker one G2 comment"
    And the field "Mark (0–10)" matches value "8"
    And I set the field "G2 criterion remark" to "Agreed G2 comment"
    When I press "Save and finalise"
    # (2 + 8) / 15 = 66.67%.
    Then I should see "66.67" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "[data-behat-markstage='final_agreed'] a" "css_element"
    And the field "G1 criterion remark" matches value "Marker one G1 comment"
    And the field "Mark (0–5)" matches value "2"
    And the field "G2 criterion remark" matches value "Agreed G2 comment"
    And the field "Mark (0–10)" matches value "8"

  @javascript
  Scenario: Copy marker criterion levels and feedback to agreed when using a rubric
    Given I am on the "Rubric CW" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Rubric"
    And I wait until the page is ready
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name        | Behat rubric       |
      | Description | Rubric description |
    And I define the following rubric:
      | R1 | Bad | 1 | Ok | 2 | Good | 3 |
      | R2 | Bad | 1 | Ok | 2 | Good | 3 |
    And I press "Save rubric and make it ready"
    And I am on the "Rubric CW" "coursework activity" page
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | R1 | 3 | Marker one R1 comment |
      | R2 | 1 | Marker one R2 comment |
    And I press "Save and finalise"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I wait until the page is ready
    And I grade by filling the rubric with:
      | R1 | 1 | Marker two R1 comment |
      | R2 | 2 | Marker two R2 comment |
    And I press "Save and finalise"
    When I follow "Agree marking"
    And I wait until the page is ready
    Then the rubric level with "1" points is not checked for criterion "R1"
    And the rubric level with "3" points is not checked for criterion "R1"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value ""
    # Copy marker 2 for R1.
    When I click on "button[aria-label='Copy Marker 2 mark and feedback for R1 to agreed']" "css_element"
    Then the rubric level with "1" points is checked for criterion "R1"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value "Marker two R1 comment"
    # Other criteria are not affected.
    And the rubric level with "1" points is not checked for criterion "R2"
    And the rubric level with "2" points is not checked for criterion "R2"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R2:')]" matches value ""
    # Copying another marker replaces what is there.
    When I click on "button[aria-label='Copy Marker 1 mark and feedback for R1 to agreed']" "css_element"
    Then the rubric level with "3" points is checked for criterion "R1"
    And the rubric level with "1" points is not checked for criterion "R1"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value "Marker one R1 comment"
    # Copying the level that is already selected keeps it selected.
    When I click on "button[aria-label='Copy Marker 1 mark and feedback for R1 to agreed']" "css_element"
    Then the rubric level with "3" points is checked for criterion "R1"
    When I click on "button[aria-label='Copy Marker 2 mark and feedback for R2 to agreed']" "css_element"
    Then the rubric level with "2" points is checked for criterion "R2"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R2:')]" matches value "Marker two R2 comment"
    When I press "Save and finalise"
    # (3 + 2) / 6 = 83.33%.
    Then I should see "83.33" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "[data-behat-markstage='final_agreed'] a" "css_element"
    And I wait until the page is ready
    And the rubric level with "3" points is checked for criterion "R1"
    And the rubric level with "2" points is checked for criterion "R2"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value "Marker one R1 comment"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R2:')]" matches value "Marker two R2 comment"

  @javascript
  Scenario: Copy to agreed is not offered when the agreed stage uses a different grading method
    Given the following "activity" exists:
      | activity          | coursework    |
      | course            | C1            |
      | name              | Simple final  |
      | numberofmarkers   | 2             |
      | deadline          | ##yesterday## |
      | finalstagegrading | 1             |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework   | finalisedstatus |
      | student1    | Simple final | 1               |
    And I am on the "Simple final" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Marking guide"
    And I wait until the page is ready
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name | Behat marking guide |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers | Maximum score |
      | G1             | Description for students | Description for markers | 5             |
    And I press "Save marking guide and make it ready"
    And I am on the "Simple final" "coursework activity" page
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I grade by filling the marking guide with:
      | G1 | 2 | Marker one G1 comment |
    And I press "Save and finalise"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I grade by filling the marking guide with:
      | G1 | 1 | Marker two G1 comment |
    And I press "Save and finalise"
    When I follow "Agree marking"
    Then I should see "Marker one G1 comment"
    And I should not see "Copy to agreed"
