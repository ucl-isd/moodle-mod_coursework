Feature: Automatic sampling using total number of students in stage 1 and 2

  As a course administrator setting up a coursework instance for a large group of students
  I want to be able to specify a set of rules that will automatically create a total sample for second markers
  So that this process does not need to be done manually, wasting lots of time.

  Background:
    Given there is a course
    And I am logged in as a manager
    And the manager has a capability to allocate students in samplings
    And there is a coursework
    And there is a student
    And there is another student
    And there is a teacher
    And there is another teacher

  Scenario: Automatically allocating a total for stage 2 based on stage 1
    Given the coursework "numberofmarkers" setting is "2" in the database
    And the coursework "samplingenabled" setting is "1" in the database
   And I am on the allocations page
    When I enable automatic sampling for stage 2
    And I enable total rule for stage 2
    And I select 50% of total students in stage 1
    And I save sampling strategy
   Then a student or another student should be automatically included in sample for stage 2



  Scenario: Automatically allocating a total for stage 3 based on stage 2
    Given the coursework "numberofmarkers" setting is "3" in the database
    And the coursework "samplingenabled" setting is "1" in the database
    And I am on the allocations page
    When I enable automatic sampling for stage 2
    And I enable total rule for stage 2
    And I select 100% of total students in stage 1
    And I enable automatic sampling for stage 3
    And I enable total rule for stage 3
    And I select 50% of total students in stage 2
    And I save sampling strategy
    Then a student or another student should be automatically included in sample for stage 3



  Scenario: Automatically allocating a total for stage 3 based on stage 1
    Given the coursework "numberofmarkers" setting is "3" in the database
    And the coursework "samplingenabled" setting is "1" in the database
    And I am on the allocations page
    When I enable automatic sampling for stage 3
    And I enable total rule for stage 3
    And I select 50% of total students in stage 1
    And I save sampling strategy
    Then a student or another student should be automatically included in sample for stage 3










