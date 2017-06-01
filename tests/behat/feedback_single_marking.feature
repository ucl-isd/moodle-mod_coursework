@mod @mod_coursework
Feature: Adding and editing single feedback

    In order to provide students with a fair final grade that combines the component grades
    As a course leader
    I want to be able to edit the final grade via a form

    Background:
        Given there is a course
        And there is a coursework
        And the coursework "numberofmarkers" setting is "1" in the database
        And there is a student
        And there is a teacher
        And the student has a submission
        And the teacher has a capability to edit their own initial feedbacks
        And I log in as the teacher

    Scenario: Setting the final feedback grade
        Given the submission is finalised
        And the coursework deadline has passed
        And I visit the coursework page
        When I click the new single final feedback button for the student
        When I grade the submission using the simple form
        Then I should be on the coursework page
        And I should see the final grade on the single marker page

    Scenario: Setting the final feedback comment
        Given the submission is finalised
        And I visit the coursework page
        When I click the new single final feedback button for the student
        When I grade the submission using the simple form
        Then I should be on the coursework page
        When I click the edit final feedback button
        Then I should see the grade comment in the form on the page
        And I should see the grade in the form on the page

    Scenario: I should not see the feedback icon when the submisison has not been finalised
        And I visit the coursework page
        Then I should not see a link to add feedback

    Scenario: Editing someone elses grade
        Given the submission is finalised
        And there is feedback for the submission from the teacher
        And I visit the coursework page
        When I click the edit final feedback button
        Then I should see the grade comment as "Blah" in the form on the page
        And I should see the other teacher's grade in the form on the page

