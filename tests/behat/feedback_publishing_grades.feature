@mod @mod_coursework @mod_coursework_feedback_publishing_grades
Feature: publishing grades to the students

    In order that the students receive their final grades
    As a manager
    I want to be able to publish the grades when I am ready to

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "1" in the database
    And the coursework "blindmarking" setting is "0" in the database
    And the coursework "moderationenabled" setting is "0" in the database
    And there is a student
    And the student has a submission
    And the submission is finalised

  @javascript
  Scenario: Not publishing with double marking hides feedback from the student
    Given there is a teacher
    And there is another teacher
    And the coursework "numberofmarkers" setting is "2" in the database
    And there are feedbacks from both teachers
    And I am logged in as a manager
    When I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I grade the submission as 56 using the simple form
    Then I visit the coursework page
    And I click the edit final feedback button
    And the field "Mark" matches value "56"
    And the grade comment textarea field matches "New comment"
    And I log out

    And I log in as the student
    And I visit the coursework page
    Then I should not see the final grade on the student page
    And I should not see the grade comment on the student page

  @javascript
  Scenario: Deliberate publishing with double marking shows feedback to the student
    Given there is a teacher
    And there is another teacher
    And the coursework "numberofmarkers" setting is "2" in the database
    And there are feedbacks from both teachers
    And I am logged in as a manager

    When I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I grade the submission as 56 using the simple form
    And I visit the coursework page
    And I press the release marks button
    And I log out

    And I log in as the student
    And I visit the coursework page
    Then I should see the final grade on the student page
    And I should see the grade comment on the student page
