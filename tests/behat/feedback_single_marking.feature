@mod @mod_coursework @mod_coursework_feedback_single_marking
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

  @javascript
  Scenario: Setting the final feedback grade and comment
    Given the submission is finalised
    And the coursework deadline has passed
    And I visit the coursework page
    And I click on the add feedback button
    And I grade the submission as 56 using the simple form
    Then I visit the coursework page
    And I should see the final grade as 56
    And I click the edit feedback button
    And the field "Mark" matches value "56"
    And the grade comment textarea field matches "New comment"

  Scenario: I should not see the feedback icon when the submission has not been finalised
    And I visit the coursework page
    Then I should not see a link to add feedback

  Scenario: Editing someone else's grade
    Given the submission is finalised
    And there is feedback for the submission from the teacher
    And I log out
    And I log in as "admin"
    And I visit the coursework page
    When I click the edit feedback button
    And the field "Mark" matches value "58"
    And the field with xpath "//textarea[@id='id_feedbackcomment']" matches value "Blah"
    And I set the field "Mark" to "50"
    And I press "Save and finalise"
    Then I should see "50" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student1" "table_row"

    When I follow "Release the marks"
    And I log out
    And I log in as a student
    And I visit the coursework page
    Then I should not see "Admin User" in the ".coursework-feedback" "css_element"
    But I should see "teacher teacher2" in the ".coursework-feedback" "css_element"

  Scenario: Student cannot see marker when assessor anonymity is enabled
    Given the submission is finalised
    And there is finalised feedback for the submission from the teacher
    And grades have been released
    And I log out
    When the coursework "assessoranonymity" setting is "1" in the database
    And I log in as a student
    And I visit the coursework page
    Then I should not see "Admin User" in the ".coursework-feedback" "css_element"
    And I should not see "teacher teacher2" in the ".coursework-feedback" "css_element"
    But I should see "Marker 1" in the ".coursework-feedback" "css_element"
