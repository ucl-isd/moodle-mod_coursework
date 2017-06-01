Feature: Automatic sample based on range set grades using marking of students in stage 1 and 2

  As a manager, I want to be able to automatically allocate assessors to students
  using a set of grade rules with upper and lower limits
  for a large group of students so that the marking is fairly distributed
  so they mark more evenly and randomly.

  Background:
    Given there is a course
    And I am logged in as a manager
    And there is a coursework
    And there is a student
    And the student has a submission
    And there is another student
    And another student has another submission
    And there is a teacher
    And the coursework "numberofmarkers" setting is "3" in the database
    And the coursework "samplingenabled" setting is "1" in the database
    And the coursework deadline has passed
    And I log out
    Given I am logged in as a teacher
    And I visit the coursework page
    And I click on the new feedback button for assessor 1
    And I grade the submission as 56 using the simple form
    And I click on the new feedback button for assessor 1 for another student
    And I grade the submission as 45 using the simple form
    And I log out


  Scenario: Automatically allocating a set of students within specified grade rule range in stage 2 based on stage 1 grades
    Given I am logged in as a manager
    And I am on the allocations page
    And I enable automatic sampling for stage 2
    And show me the page
    And I enable grade range rule 1 for stage 2
    And I select limit type for grade range rule 1 in stage 2 as "grade"
    And I select "from" grade limit for grade range rule 1 in stage 2 as "50"
    And I select "to" grade limit for grade range rule 1 in stage 2 as "100"
    And I save sampling strategy
    Then a student should be automatically included in sample for stage 2
    And another student should not be automatically included in sample for stage 2

    #Then I add grade range rule for stage 2
    #And I enable grade range rule 2 for stage 2
    #And I select limit type for grade range rule 2 in stage 2 as "grade"
    #And I select "from" grade limit for grade range rule 1 in stage 2 as "40"
    #And I select "to" grade limit for grade range rule 1 in stage 2 as "50"
    #And show me the page
    #And I save sampling strategy
    #Then a student should be automatically included in sample for stage 2
    #And another student should be automatically included in sample for stage 2



  Scenario: Automatically allocating a set of students within specified percentage rule range in stage 3 based on stage 2 grades
    Given I am logged in as a manager
    And I am on the allocations page
    And I enable automatic sampling for stage 2
    And I enable total rule for stage 2
    And I select 100% of total students in stage 1
    And I save sampling strategy
    And I visit the coursework page
    And I click on the new feedback button for assessor 2
    And I grade the submission as 60 using the simple form
    And I click on the new feedback button for assessor 2 for another student
    And I grade the submission as 40 using the simple form
    And I log out
    And I am logged in as a manager
    And I am on the allocations page
    When I enable automatic sampling for stage 3
    And I enable grade range rule 1 for stage 3
    And I select limit type for grade range rule 1 in stage 3 as "percentage"
    And I select "from" grade limit for grade range rule 1 in stage 3 as "60"
    And I select "to" grade limit for grade range rule 1 in stage 3 as "70"
    And I save sampling strategy
    Then a student should be automatically included in sample for stage 3
    And another student should not be automatically included in sample for stage 3










