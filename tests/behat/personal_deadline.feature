@mod @mod_coursework
Feature: When "Use the personal deadline" is enabled the deadline date should reflect any personal deadlines

  Background:
    Given there is a course
    And there is a coursework
    And the coursework deadline date is "##+1 week##"
    And the coursework "personaldeadlineenabled" setting is "1" in the database
    And there is a student
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
