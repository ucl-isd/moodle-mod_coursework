@mod @mod_coursework @mod_coursework_marking_guide_percent_grades
Feature: Marking guide percentage grades entry
  Markers can enter element grades as percentages instead of the usual fractions

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And the coursework "allowenterguidegradesaspercent" setting is "1" in the database
    And there is a teacher
    And there is a student
    And there is another teacher
    And the student has a submission
    And the submission is finalised
    And the coursework deadline has passed

    And I log in as "admin"
    And I visit the coursework page

    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Marking guide"
    And I wait until the page is ready
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name | Behat marking form |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers  | Maximum score |
      | Criterion 1     | Description for students | Description for markers | 30            |
      | Criterion 2     | Description for students | Description for markers | 20            |
      | Criterion 3     | Description for students | Description for markers | 50            |
    And I press "Save marking guide and make it ready"
    And I log out

  @javascript
  Scenario: Marker enters grades as fractions in usual way (no percent grades allowed by site admin).
    Given the coursework "allowenterguidegradesaspercent" setting is "0" in the database
    And I log in as the teacher
    And I visit the coursework page
    And I follow "Add mark"
    And I wait until the page is ready
    And I should see "Mark (0–30)"
    And I should see "Mark (0–20)"
    And I should see "Mark (0–50)"
    And I should not see "Mark %"
    And I should not see "Enter marks as %"

    # Enter a grade of 6/30 + 10/20 + 25/50 = 41/100
    And I grade by filling the marking guide with:
      | Criterion 1 | 6  |  |
      | Criterion 2 | 10 |  |
      | Criterion 3 | 25 |  |
    And I press "Save and finalise"

    And I visit the coursework page
    And I should see "41" in the "student student2" "table_row"
    And I log out

  @javascript
  Scenario: Marker enters grades as percentages, or as fractions even though percentage grades are allowed.
    Given the following "user preferences" exist:
      | user     | preference                            | value |
      | user1    | coursework_guide_enter_percent_grades | 1     |
    And I log in as the teacher
    And I visit the coursework page
    And I follow "Add mark"
    And I wait until the page is ready
    And I should see "Mark (0–30)"
    And I should see "Mark (0–20)"
    And I should see "Mark (0–50)"
    And I should see "Mark %"

    # Enter a grade of 50% of /30 (15 marks) + 20 % of /20 (4 marks) + 60% of /50 (30 marks) = 49/100
    And I set the field "Mark %" in the "Criterion 1" "table_row" to "50"
    And I set the field "Mark %" in the "Criterion 2" "table_row" to "20"
    And I set the field "Mark %" in the "Criterion 3" "table_row" to "60"
    And I wait until the page is ready
    And I wait "1" seconds
    # Fractional marks are calculated and added to form fields by JS, based on percentages entered.
    And the field "Mark (0–30)" matches value "15"
    And the field "Mark (0–20)" matches value "4"
    And the field "Mark (0–50)" matches value "30"
    # Total % mark also calculated and shown.
    And I should see "49%" in the ".total-mark-container" "css_element"
    And I press "Save and finalise"
    And I should see "Feedback saved"

    And I visit the coursework page
    And I should see "49" in the "student student2" "table_row"
    And I log out

    # Now add as scores, with percentages switched off, even though percentage grades are allowed.
    And I log in as the other teacher
    And I visit the coursework page
    And I follow "Add mark"
    # Percentage marks by default - toggle the input to check it works.
    And the field "Enter marks as %" matches value "0"
    And I click on "Enter marks as %" "checkbox"
    And the field "Enter marks as %" matches value "1"
    And I click on "Enter marks as %" "checkbox"
    And the field "Enter marks as %" matches value "0"

    # Enter a grade of 50% of /30 (15 marks) + 20 % of /20 (4 marks) + 60% of /50 (30 marks) = 49/100
    And I set the field "Mark (0–30)" in the "Criterion 1" "table_row" to "15"
    And I set the field "Mark (0–20)" in the "Criterion 2" "table_row" to "4"
    And I set the field "Mark (0–50)" in the "Criterion 3" "table_row" to "30"
    And I wait until the page is ready
    And I press "Save and finalise"
    And I should see "Feedback saved"

    And I visit the coursework page
    And I should see "49" in the "student student2" "table_row"
