@mod @mod_coursework @mod_coursework_feedback_edit_specified_time
Feature: Allow markers to edit their marking but only during specific marking stages

  As an initial marker
  I want to be able to edit my initial marking if I have made a mistake.
  So that if the marking stage is at final agreed grading there is a time window for initial marks edition to happen


  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allocationenabled" setting is "1" in the database
    And the coursework "numberofmarkers" setting is "2" in the database
    And the coursework "gradeeditingtime" setting is "30" in the database
    And there is a teacher
    And there is another teacher
    And there is a student
    And there is a manager
    And the student has a submission
    And the submission is finalised

  @javascript
  Scenario: Edit own initial feedback before delayed time
    Given there are feedbacks from both teachers
    And I log in as the teacher
    And I visit the coursework page
    And I expand the coursework grading row
    Then I should see the edit feedback button for the teacher's feedback

  @javascript
  Scenario: Edit own initial feedback after delayed time
    Given there are feedbacks from both teachers
    And I wait "35" seconds
    And I log in as the teacher
    And I visit the coursework page
    And I expand the coursework grading row
    Then I should not see the edit feedback button for the teacher's feedback

  @javascript
  Scenario: Automatic agreement before delayed time
    Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database

    And there are no allocations in the db
    And I log in as a manager
    And I visit the allocations page
    And I manually allocate the student to the teacher
    And I manually allocate the student to the other teacher for the second assessment
    And I save everything
    And I log out

    And I am logged in as a teacher
    And I visit the coursework page
    And I expand the coursework grading row
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 67 using the ajax form
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I expand the coursework grading row
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 63 using the ajax form
    And I visit the coursework page
    Then I should not see the final grade on the multiple marker page

  @javascript
  Scenario: Automatic agreement after delayed time
    Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database

    And there are no allocations in the db
    And I log in as a manager
    And I visit the allocations page
    And I manually allocate the student to the teacher
    And I manually allocate the student to the other teacher for the second assessment
    And I save everything
    And I log out

    And I log in as a teacher
    And I visit the coursework page
    And I expand the coursework grading row 1
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 67 using the ajax form
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I expand the coursework grading row 1
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 63 using the ajax form
    And I wait "50" seconds
    And I visit the coursework page
    Then I should see the final grade as 67 on the multiple marker page
