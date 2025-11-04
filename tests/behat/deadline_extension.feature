@mod @mod_coursework @RVC_PT_83106618 @mod_coursework_deadline_extension @javascript
Feature: Deadlines extensions for submissions

  As an OCM admin
  I can add allow students to submit after the deadline
  So that late work can still be given a grade

  Background:
    Given there is a course
    And there is a coursework
    And the coursework individual extension option is enabled
    And there is a student

  Scenario: The student can submit after the deadline when the start date is disabled
    Given the coursework deadline has passed
    And there is an extension for the student that allows them to submit
    When I log in as a student
    And I visit the coursework page
    Then "Upload your submission" "link" should exist
    And I should see extension date "##+1 week##%d %B %Y##"

  @javascript
  Scenario: The student can not submit when the start date is in the future
    Given the coursework deadline has passed
    And there is an extension for the student which has expired
    When I log in as a student
    And I visit the coursework page
    Then I should not see "Upload your submission"

  @javascript
  Scenario: The teacher can add a deadline extension to an individual submission
    Given the coursework deadline has passed
    And I log in as a manager
    And I visit the coursework page
    And I press "Actions"
    And I follow "Submission extension"
    And I set the following fields to these values:
      | extended_deadline[day]    | 1       |
      | extended_deadline[month]  | January |
      | extended_deadline[year]   | 2027    |
      | extended_deadline[hour]   | 08      |
      | extended_deadline[minute] | 00      |
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    And I should see "1 January 2027, 8:00 AM" in the "student student1" "table_row"
    Then I visit the coursework page
    And I should see "1 January 2027, 8:00 AM" in the "student student1" "table_row"

  @javascript
  Scenario: The teacher can edit a deadline extension to an individual submission
    Given the coursework deadline has passed
    And there is an extension for the student which has expired
    And I log in as a manager
    And I visit the coursework page
    And I press "Actions"
    And I follow "Submission extension"
    And I set the following fields to these values:
      | extended_deadline[day]    | 1       |
      | extended_deadline[month]  | January |
      | extended_deadline[year]   | 2027    |
      | extended_deadline[hour]   | 08      |
      | extended_deadline[minute] | 00      |
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    And I should see "1 January 2027, 8:00 AM" in the "student student1" "table_row"
    Then I visit the coursework page
    And I should see "1 January 2027, 8:00 AM" in the "student student1" "table_row"
