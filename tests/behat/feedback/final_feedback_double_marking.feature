@mod @mod_coursework
Feature: Adding and editing final feedback

    In order to provide students with a fair final grade that combines the component grades
    As a course leader
    I want to be able to edit the final grade via a form

    Background:
        Given there is a course
        And there is a coursework
        And there is a teacher
        And there is another teacher
        And the coursework "numberofmarkers" setting is "2" in the database
        And there is a student
        And the student has a submission
        And the submission is finalised

    Scenario: Setting the final feedback grade
        Given there are feedbacks from both teachers
        And I am logged in as a manager
        And I visit the coursework page
        When I click the new multiple final feedback button for the student
        When I grade the submission using the simple form
        Then I should be on the coursework page
        And I should see the final grade on the multiple marker page

    Scenario: Setting the final feedback comment
        Given there are feedbacks from both teachers
        And I am logged in as a manager
        And I visit the coursework page
        When I click the new multiple final feedback button for the student
        When I grade the submission using the simple form
        Then I should be on the coursework page
        When I click the edit final feedback button
        Then I should see the grade comment in the form on the page
        And I should see the grade in the form on the page

    Scenario: I can be both an initial assessor and the manager who agrees grades
        And managers do not have the manage capability
        Given I am logged in as a manager
        And there are feedbacks from both me and another teacher
        And I visit the coursework page
        When I click the new multiple final feedback button for the student
        And I grade the submission using the simple form
        Then I should be on the coursework page

    Scenario: Editing final feedback from others
        And managers do not have the manage capability
        Given I am logged in as a manager
        And there are feedbacks from both me and another teacher
        And there is final feedback from the other teacher
        When I visit the coursework page
        When I click the edit final feedback button
        And I should see the other teacher's final grade in the form on the page
        And I grade the submission using the simple form
        Then I should be on the coursework page


