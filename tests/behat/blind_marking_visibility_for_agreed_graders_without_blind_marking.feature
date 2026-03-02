@mod @mod_coursework
Feature: visibility of agreed graders without blind marking

    As an agreed grader
    I want to be certain that teachers (and me) are unable to see the grades of other
    teachers before the agreement phase
    So that we are not influenced by one another or confused over what to do

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers            | 2          |
    And there is a teacher
    And there is another teacher
    And there is a student
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: agreed graders can see other feedbacks before they have done their own
    Given teachers have the add agreed grade capability
    And the coursework "viewinitialgradeenabled" setting is "1" in the database
    And I am logged in as the other teacher
    And there is feedback for the submission from the teacher
    When I am on the "Coursework" "coursework activity" page
    Then I should see "58" in the "student student3" "table_row"

  Scenario: agreed graders can view the feedback of the other assessors when all done
    the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And teachers have the add agreed grade capability
    And I am logged in as the other teacher
    When I am on the "Coursework" "coursework activity" page
    Then I should see "63" in the "student student3" "table_row"
    And I should see "67" in the "student student3" "table_row"
    Then I should see "teacher teacher1" in the "student student3" "table_row"
    And I should see "otherteacher teacher2" in the "student student3" "table_row"
    Then I click on "67" "link" in the "student student3" "table_row"
    And I should see "New comment here"
