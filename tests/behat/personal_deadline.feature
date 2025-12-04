@mod @mod_coursework @mod_coursework_personal_deadline
Feature: When "Use the personal deadline" is enabled the deadline date should reflect any personal deadlines

  As a manager I can add personal deadlines

  Background:
    Given there is a course
    And there is a coursework
    And the coursework deadline date is "##+1 week##"
    And the coursework "personaldeadlineenabled" setting is "1" in the database
    And there is a student called "John1"
    And there is another student

  Scenario: Student with personal deadline
    Given I am logged in as a student
    And the student personaldeadline is "##+2 weeks##"
    And I visit the coursework page
    Then I should see due date "##+2 weeks##%d %B %Y##"
    But I should not see "This is the default deadline that will be used if personal deadline was not specified"

  Scenario: Student without personal deadline
    Given I log in as the other student
    And I visit the coursework page
    Then I should see due date "##+1 week##%d %B %Y##"
    But I should not see "This is the default deadline that will be used if personal deadline was not specified"

  Scenario: Teacher sees default date with message
    Given I am logged in as a teacher
    And I visit the coursework page
    Then I should see due date "##+1 week##%d %B %Y##"
    And I should see "This is the default deadline that will be used if personal deadline was not specified"

  @javascript
  Scenario: The teacher can add a personal deadline to an individual user
    Given the coursework deadline has passed
    And I log in as a manager
    And I visit the coursework page
    And I click on the "Actions" button in the table row containing "John1"
    And I wait until the page is ready
    And I wait "1" seconds
    And I click on "Personal deadline" "link"
    And I wait until the page is ready
    And I should see "New personal deadline for John1 student1"
    And I set the field "Personal deadline" to "##+2 weeks, 8:00 AM##"
    And I wait "2" seconds
    And I click on "Save" "button" in the "Personal deadline" "dialogue"
    And I wait until the page is ready
    And I should see "Personal deadline" in the table row containing "John1"
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the table row containing "John1"
    # Check still appears on page re-load
    Then I visit the coursework page
    And I should see "Personal deadline" in the table row containing "John1"
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the table row containing "John1"
