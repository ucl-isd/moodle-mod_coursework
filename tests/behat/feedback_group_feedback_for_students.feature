@mod @mod_coursework @mod_coursework_feedback_group_feedback_for_students
Feature: Students see feedback on group assignments

    As a student
    I want to be able to see the feedback for the group assignment even if I did not submit it
    So that I know what my marks are and can improve my work

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the coursework "usegroups" setting is "1" in the database
    And the coursework is set to double marker
    And there is a manager
    And there is a student
    And the student is a member of a group
    And there is another student
    And the other student is a member of the group
    And the group has a submission
    And the submission is finalised
    And there is final feedback
    And the grades have been published

  Scenario: I can see the published grade when someone else submitted
    Given I am logged in as the other student
    When I am on the "Coursework" "coursework activity" page
    Then I should see the grade for the group submission
    And I should see the feedback for the group submission

  Scenario: I can see the published grade when I submitted
    Given I am logged in as the student
    When I am on the "Coursework" "coursework activity" page
    Then I should see the grade for the group submission
    And I should see the feedback for the group submission

#  TODO fix this broken test.
#  @broken
#  Scenario: I can see the published grade in the gradebook when someone else submitted
#    Given I am logged in as the other student
#    And I am on the "Coursework" "coursework activity" page
#    When I visit the gradebook page
#    Then I should see the grade in the gradebook

#  TODO fix this broken test.
#  @broken
#  Scenario: I can see the published grade in the gradebook when I submitted
#    Given I am logged in as the student
#    When I visit the gradebook page
#    Then I should see the grade in the gradebook
