@mod @mod_coursework
Feature: general feedback

    As a teacher
    I want to be able to provide some general feedback for all of the students before their individual feedback is released
    So that they can prepare effectively for upcoming exams or assignments

    Scenario: enabling general feedback shows the place for managers to enter feedback
        Given there is a course
        And there is a coursework
        And the coursework general feedback is enabled
        And I am logged in as an manager
        When I visit the coursework page
        Then I should see "Add general feedback"

    Scenario: disabling general feedback does not hide the place for managers to enter feedback
        Given there is a course
        And there is a coursework
        And the coursework general feedback is disabled
        And I am logged in as an manager
        When I visit the coursework page
        Then I should see "Add general feedback"

    Scenario: enabling general feedback shows students the feedback deadline
        Given there is a course
        And there is a coursework
        And the coursework general feedback is enabled
        And I am logged in as a student
        And I have a submission
        When I visit the coursework page
        And I should see "General feedback deadline"

    Scenario: enabling general feedback shows students the feedback when the deadline has passed
        Given there is a course
        And there is a coursework
        And the coursework general feedback is enabled
        And there is some general feedback
        And the general feedback deadline has passed
        And I am logged in as a student
        And I have a submission
        When I visit the coursework page
        Then I should see "General feedback"

    Scenario: disabling general feedback hides the feedback from students
        Given there is a course
        And there is a coursework
        And the coursework general feedback is disabled
        And I am logged in as a student
        When I visit the coursework page
        Then I should not see "General feedback:"
        And I should not see "General feedback deadline"

    Scenario: Users without permission cannot add or edit general feedback
        Given there is a course
        And there is a coursework
        And the coursework general feedback is enabled
        And I am logged in as an editing teacher
        When I visit the coursework page
        Then I should not see "Edit general feedback"
        And I should not see "Add general feedback"



