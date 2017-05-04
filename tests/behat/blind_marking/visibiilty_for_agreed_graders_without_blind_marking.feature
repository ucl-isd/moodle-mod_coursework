@mod @mod_coursework
Feature: visibility of agreed graders without blind marking

    As an agreed grader
    I want to be certain that teachers (and me) are unable to see the grades of other
    teachers before the agreement phase
    So that we are not influenced by one another or confused over what to do

    Background:
        Given there is a course
        And there is a coursework
        And there is a teacher
        And there is another teacher
        And the coursework "numberofmarkers" setting is "2" in the database
        And there is a student
        And the student has a submission
        And the submission is finalised

    Scenario: agreed graders can see other feedbacks before they have done their own
        Given teachers have the add agreed grade capability
        And the coursework "viewinitialgradeenabled" setting is "1" in the database
        And I am logged in as the other teacher
        And there is feedback for the submission from the teacher
        When I visit the coursework page
        Then I should see the grade from the teacher in the assessor table

    Scenario: agreed graders can view the feedback of the other assessors when all done
        Given there are feedbacks from both teachers
        And teachers have the add agreed grade capability
        And I am logged in as the other teacher
        And I visit the coursework page
        When I click on the view icon for the first initial assessor's grade
        Then I should see the first initial assessors grade and comment