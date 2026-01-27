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
    Then I should see "58" in the "student student3" "table_row"

  Scenario: agreed graders can view the feedback of the other assessors when all done
    Given there are feedbacks from both teachers
    And teachers have the add agreed grade capability
    And I am logged in as the other teacher
    When I visit the coursework page
    Then I should see "63" in the "student student3" "table_row"
    And I should see "67" in the "student student3" "table_row"
    Then I should see "teacher teacher1" in the "student student3" "table_row"
    And I should see "otherteacher teacher2" in the "student student3" "table_row"
    Then I click on "67" "link" in the "student student3" "table_row"
    And I should see "New comment here"
