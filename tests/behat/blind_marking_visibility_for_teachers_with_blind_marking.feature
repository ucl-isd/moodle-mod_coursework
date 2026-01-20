@mod @mod_coursework
Feature: Visibility for teachers with blind marking

  As a manager
  I want to be able to prevent teachers from seeing each others' marks
  So that I can be sure that they are not influenced by each other and the marking is fair

  Background:
    Given there is a course
    And there is a coursework

  Scenario: The student names are hidden from teachers in the user cells
    Given blind marking is enabled
    And I am logged in as a teacher
    And there is a student
    When I visit the coursework page
    Then I should not see the student's name in the user cell
    And I should not see the student's picture in the user cell

  Scenario: The student names are not hidden from teachers in the user cells
    Given I am logged in as a teacher
    And there is a student
    When I visit the coursework page
    Then I should see the student's name in the user cell
    And I should see the student's picture in the user cell

  @javascript
  Scenario: The user names are hidden from teachers in the group cells
    Given blind marking is enabled
    And I am logged in as a teacher
    And there is a student
    And group submissions are enabled
    And the student is a member of a group
    And the group is part of a grouping for the coursework
    When I visit the coursework page
    And I should see "View members" in the "My group" "table_row"
    And I click on "View members" "button"
    Then I should not see "student student2" in the ".dropdown-menu.show" "css_element"
    Then I should see "Members are hidden" in the ".dropdown-menu.show" "css_element"

  Scenario: Teachers cannot see other initial grades before final grading happens
    Given blind marking is enabled
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a teacher
    And there is another teacher
    And there is a student
    And the student has a submission
    And the submission is finalised
    And there are feedbacks from both teachers
    And I am logged in as the other teacher
    When I visit the coursework page
    Then I should not see "67" in the "Hidden" "table_row"
    But I should see "Marked" in the "Hidden" "table_row"
    And I should see "63" in the "Hidden" "table_row"
    And I log out
    Then I am logged in as a teacher
    When I visit the coursework page
    Then I should not see "63" in the "Hidden" "table_row"
    But I should see "Marked" in the "Hidden" "table_row"
    And I should see "67" in the "Hidden" "table_row"