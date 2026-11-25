@mod @mod_coursework @mod_coursework_copy_to_agreed
Feature: Copy a marker's criterion feedback and calculate average marks for the agreed mark
  In order to agree marks more quickly
  As a user agreeing marks with a marking guide or rubric
  I need to be able to copy an individual marker's criterion feedback into the agreed marking form
  And calculate the average criterion mark when using a marking guide

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
  Scenario: Copy marker feedback and calculate the average mark to agreed when using a marking guide
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
    # Copy marker 2 for G1: only the feedback is copied, not the mark.
    When I click on "button[aria-label='Copy Marker 2 feedback for G1 to agreed']" "css_element"
    Then the field "G1 criterion remark" matches value "Marker two G1 comment"
    And the field "Mark (0–5)" matches value ""
    # Other criteria are not affected.
    And the field "G2 criterion remark" matches value ""
    And the field "Mark (0–10)" matches value ""
    # Copying another marker appends their feedback.
    When I click on "button[aria-label='Copy Marker 1 feedback for G1 to agreed']" "css_element"
    Then the field "G1 criterion remark" matches multiline:
      """
      Marker two G1 comment

      Marker one G1 comment
      """
    And the field "Mark (0–5)" matches value ""
    # Copying the same feedback again does not duplicate it.
    When I click on "button[aria-label='Copy Marker 1 feedback for G1 to agreed']" "css_element"
    Then the field "G1 criterion remark" matches multiline:
      """
      Marker two G1 comment

      Marker one G1 comment
      """
    # Calculate the average mark for G1 only: (2 + 1) / 2 = 1.5.
    When I click on "Calculate average grade" "link" in the "G1" "table_row"
    Then the field "Mark (0–5)" matches value "1.5"
    And the field "Mark (0–10)" matches value ""
    # Calculating the average does not touch feedback.
    And the field "G2 criterion remark" matches value ""
    # Calculate the average mark for G2: (8 + 4) / 2 = 6.
    When I click on "Calculate average grade" "link" in the "G2" "table_row"
    Then the field "Mark (0–10)" matches value "6"
    And I set the field "G2 criterion remark" to "Agreed G2 comment"
    When I press "Save and finalise"
    # (1.5 + 6) / 15 = 50%.
    Then I should see "50" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "[data-behat-markstage='final_agreed'] a" "css_element"
    And the field "G1 criterion remark" matches multiline:
      """
      Marker two G1 comment

      Marker one G1 comment
      """
    And the field "Mark (0–5)" matches value "1.5"
    And the field "G2 criterion remark" matches value "Agreed G2 comment"
    And the field "Mark (0–10)" matches value "6"

  @javascript
  Scenario: Copy marker feedback only to agreed when using a rubric
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
    # The average grade calculation is only offered for marking guides.
    Then I should not see "Calculate average grade"
    And the rubric level with "1" points is not checked for criterion "R1"
    And the rubric level with "3" points is not checked for criterion "R1"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value ""
    # Copy marker 2 for R1: only the feedback is copied, no level is selected.
    When I click on "button[aria-label='Copy Marker 2 feedback for R1 to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value "Marker two R1 comment"
    And the rubric level with "1" points is not checked for criterion "R1"
    And the rubric level with "2" points is not checked for criterion "R1"
    And the rubric level with "3" points is not checked for criterion "R1"
    # Other criteria are not affected.
    And the rubric level with "1" points is not checked for criterion "R2"
    And the rubric level with "2" points is not checked for criterion "R2"
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R2:')]" matches value ""
    # Copying the same feedback again does not duplicate it.
    When I click on "button[aria-label='Copy Marker 2 feedback for R1 to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R1:')]" matches value "Marker two R1 comment"
    When I click on "button[aria-label='Copy Marker 1 feedback for R2 to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion R2:')]" matches value "Marker one R2 comment"
    And the rubric level with "1" points is not checked for criterion "R2"
    And the rubric level with "3" points is not checked for criterion "R2"

  @javascript
  Scenario: Copy marker feedback only to agreed when using a ranged rubric
    Given the following "activity" exists:
      | activity        | coursework    |
      | course          | C1            |
      | name            | Ranged CW     |
      | numberofmarkers | 2             |
      | deadline        | ##yesterday## |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Ranged CW  | 1               |
    And the "Ranged CW" coursework uses the following ranged rubric:
      | criterion | ranged | levels                     |
      | Knowledge | 1      | Fail:10, Pass:20, Merit:30 |
      | Analysis  | 1      | Fail:10, Pass:20, Merit:30 |
    And I am on the "Ranged CW" "coursework activity" page logged in as "admin"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I wait until the page is ready
    And I set the mark of the "Knowledge" ranged criterion to "25"
    And I set the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" to "Marker one Knowledge comment"
    And I set the mark of the "Analysis" ranged criterion to "5"
    And I set the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Analysis:')]" to "Marker one Analysis comment"
    And I press "Save and finalise"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I wait until the page is ready
    And I set the mark of the "Knowledge" ranged criterion to "15"
    And I set the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" to "Marker two Knowledge comment"
    And I set the mark of the "Analysis" ranged criterion to "28"
    And I set the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Analysis:')]" to "Marker two Analysis comment"
    And I press "Save and finalise"
    When I follow "Agree marking"
    And I wait until the page is ready
    # The average grade calculation is only offered for marking guides.
    Then I should not see "Calculate average grade"
    And the mark of the "Knowledge" ranged criterion should be ""
    And no level of the "Knowledge" ranged criterion should be selected
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" matches value ""
    # Copy marker 2 for Knowledge: only the feedback is copied, no mark is set.
    When I click on "button[aria-label='Copy Marker 2 feedback for Knowledge to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" matches value "Marker two Knowledge comment"
    And the mark of the "Knowledge" ranged criterion should be ""
    And no level of the "Knowledge" ranged criterion should be selected
    # Other criteria are not affected.
    And the mark of the "Analysis" ranged criterion should be ""
    And no level of the "Analysis" ranged criterion should be selected
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Analysis:')]" matches value ""
    # Copying another marker appends their feedback.
    When I click on "button[aria-label='Copy Marker 1 feedback for Knowledge to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" matches multiline:
      """
      Marker two Knowledge comment

      Marker one Knowledge comment
      """
    # Copying the same feedback again does not duplicate it.
    When I click on "button[aria-label='Copy Marker 1 feedback for Knowledge to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" matches multiline:
      """
      Marker two Knowledge comment

      Marker one Knowledge comment
      """
    When I click on "button[aria-label='Copy Marker 2 feedback for Analysis to agreed']" "css_element"
    Then the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Analysis:')]" matches value "Marker two Analysis comment"
    And the mark of the "Analysis" ranged criterion should be ""
    # The copied feedback is saved with the agreed mark.
    And I set the mark of the "Knowledge" ranged criterion to "20"
    And I set the mark of the "Analysis" ranged criterion to "16"
    When I press "Save and finalise"
    # (20 + 16) / 60 = 60%.
    Then I should see "60" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "[data-behat-markstage='final_agreed'] a" "css_element"
    And I wait until the page is ready
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Knowledge:')]" matches multiline:
      """
      Marker two Knowledge comment

      Marker one Knowledge comment
      """
    And the field with xpath "//textarea[starts-with(@aria-label, 'Remark for criterion Analysis:')]" matches value "Marker two Analysis comment"
    And the mark of the "Knowledge" ranged criterion should be "20"
    And the mark of the "Analysis" ranged criterion should be "16"

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
