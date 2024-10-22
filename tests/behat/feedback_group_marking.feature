@mod @mod_coursework @mod_coursework_feedback_group_marking
Feature: Marking the group submissions applies the grades to the whole group

    As a teacher
    I want to be able to grade the submission that the group have provided
    So that the marks are awarded to all of the group members and they can
    see their feedback and grades

  Background:
    Given there is a course
    And there is a coursework
    And there is a teacher
    And there is another teacher
    And the coursework "numberofmarkers" setting is "2" in the database
    And the coursework "use_groups" setting is "1" in the database
    And there is a student
    And the student is a member of a group
    And there is another student
    And the other student is a member of the group
    And the group has a submission
    And the submission is finalised
    And there are feedbacks from both teachers
    And I am logged in as a manager

  @javascript
  Scenario: grading the submission makes the grades show up for both students in the interface
    Given I visit the coursework page
    When I click the new final feedback button for the group
    And I grade the submission as 56 using the ajax form
    Then I visit the coursework page
    And I wait "2" seconds
    And I should see the final grade for the group in the grading interface
