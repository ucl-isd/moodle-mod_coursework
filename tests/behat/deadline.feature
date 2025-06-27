@mod @mod_coursework @javascript
# Although JavaScript isn't needed for the functionality being tested, it is
# needed to hide any modals (for example, New Extension).

Feature: When there is a deadline for submissions this should appear on the activity page

  Background:
    Given there is a course
    And there is a coursework
    And the coursework deadline date is "##+1 week##"

  Scenario: A teacher should see the deadline
    Given I log in as a teacher
    And I visit the coursework page
    # Small chance this could fail if coursework above created before midnight and step below run after midnight
    Then I should see due date "##+1 week##%d %B %Y##"
    But I should not see "Extended deadline"

  Scenario: A student should see the deadline
    Given I log in as a student
    And I visit the coursework page
    Then I should see due date "##+1 week##%d %B %Y##"
    But I should not see "Extended deadline"
