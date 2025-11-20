@mod @mod_coursework
Feature: Double marking - blind
  In order to ensure double marking works correctly
  As an admin
  I want to perform the full coursework workflow with blind marking.

  Background:
    Given the following "custom field categories" exist:
      | name | component   | area   | itemid |
      | CLC  | core_course | course | 0      |
    And the following "custom fields" exist:
      | name        | shortname   | category | type |
      | Course Year | course_year | CLC      | text |
    And the following "courses" exist:
      | fullname | shortname | format | customfield_course_year |
      | Course 1 | C1        | topics | ##now##%Y##             |
    And the following "roles" exist:
      | shortname           | name                | archetype |
      | courseworkmarker    | courseworkmarker    | teacher   |
      | uclnoneditingtutor  | uclnoneditingtutor  | teacher   |
    And the following "users" exist:
      | username  | firstname | lastname | email                |
      | teacher1  | teacher   | 1        | teacher1@example.com |
      | marker1   | marker    | 1        | marker1@example.com  |
      | marker2   | marker    | 2        | marker2@example.com  |
      | marker3   | marker    | 3        | marker3@example.com  |
      | student1  | Student   | 1        | student1@example.com |
      | student2  | Student   | 2        | student2@example.com |
      | student3  | Student   | 3        | student3@example.com |
    And the following "course enrolments" exist:
      | user      | course | role             |
      | teacher1  | C1     | teacher          |
      | marker1   | C1     | teacher          |
      | marker2   | C1     | teacher          |
      | marker3   | C1     | teacher          |
      | student1  | C1     | student          |
      | student2  | C1     | student          |
      | student3  | C1     | student          |

  Scenario: Create coursework assignment with double marking
    Given I am on the "Course 1" "course" page logged in as "admin"
    And I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
      | Formative or summative?                                     | Summative - counts towards the final module mark  |
      | Description                                                 | Test coursework description                       |
      | Display description on course page                          | Yes                                               |
      | Start date                                                  | ##now##                                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##                              |
      | Use marking deadline                                        | Yes                                               |
      | Types of file that students are allowed to submit           | pdf                                               |
      | Enable plagiarism flagging                                  | Yes                                               |
      | Number of times each submission should initially be marked. | 2                                                 |
      | Marker allocation enabled                                   | Yes                                               |
      | Marker allocation strategy                                  | Manual                                            |
      | Automatic agreement of marks                                | percentage distance                               |
      | Automatic agreement range                                   | 10                                                |
      | View initial markers' grades                                | No                                                |
      | Auto-populate agreed feedback comment                       | Yes                                               |
      | Blind marking                                               | Yes                                               |
      | Marker anonymity                                            | Yes                                               |

    Then I should see "Coursework – Double marking blind"

  Scenario: Add markers
    Given I am on the "Course 1" "course" page logged in as "admin"
    And I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
      | Formative or summative?                                     | Summative - counts towards the final module mark  |
      | Description                                                 | Test coursework description                       |
      | Display description on course page                          | Yes                                               |
      | Start date                                                  | ##now##                                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##                              |
      | Use marking deadline                                        | Yes                                               |
      | Types of file that students are allowed to submit           | pdf                                               |
      | Enable plagiarism flagging                                  | Yes                                               |
      | Number of times each submission should initially be marked. | 2                                                 |
      | Marker allocation enabled                                   | Yes                                               |
      | Marker allocation strategy                                  | Manual                                            |
      | Automatic agreement of marks                                | percentage distance                               |
      | Automatic agreement range                                   | 10                                                |
      | View initial markers' grades                                | No                                                |
      | Auto-populate agreed feedback comment                       | Yes                                               |
      | Blind marking                                               | Yes                                               |
      | Marker anonymity                                            | Yes                                               |

    And I follow "Coursework – Double marking blind"
    And I follow "Add markers"
    And I follow "courseworkmarker"

    Then I should see "marker 1" in the "Potential users" "field"
    And I should see "marker 2" in the "Potential users" "field"
    And I should see "marker 3" in the "Potential users" "field"

    When I set the field "Potential users" to "marker 1 (marker1@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 2 (marker2@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 3 (marker3@example.com)"
    And I press "Add"

    Then I should see "marker 1" in the "Existing users" "field"
    And I should see "marker 2" in the "Existing users" "field"
    And I should see "marker 3" in the "Existing users" "field"
    And I should not see "marker 1" in the "Potential users" "field"
    And I should not see "marker 2" in the "Potential users" "field"
    And I should not see "marker 3" in the "Potential users" "field"

  Scenario: Allocate markers
    Given I am on the "Course 1" "course" page logged in as "admin"
    And I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
      | Formative or summative?                                     | Summative - counts towards the final module mark  |
      | Description                                                 | Test coursework description                       |
      | Display description on course page                          | Yes                                               |
      | Start date                                                  | ##now##                                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##                              |
      | Use marking deadline                                        | Yes                                               |
      | Types of file that students are allowed to submit           | pdf                                               |
      | Enable plagiarism flagging                                  | Yes                                               |
      | Number of times each submission should initially be marked. | 2                                                 |
      | Marker allocation enabled                                   | Yes                                               |
      | Marker allocation strategy                                  | Manual                                            |
      | Automatic agreement of marks                                | percentage distance                               |
      | Automatic agreement range                                   | 10                                                |
      | View initial markers' grades                                | No                                                |
      | Auto-populate agreed feedback comment                       | Yes                                               |
      | Blind marking                                               | Yes                                               |
      | Marker anonymity                                            | Yes                                               |

    And I follow "Coursework – Double marking blind"
    And I follow "Add markers"
    And I follow "courseworkmarker"

    When I set the field "Potential users" to "marker 1 (marker1@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 2 (marker2@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 3 (marker3@example.com)"
    And I press "Add"

    When I follow "Allocate markers"
    And I set the field "Allocation strategy" to "Manual"
    And I press "Apply"
    Then I should see "Please make sure markers are allocated"

    When I set the field with xpath "//tr[contains(.,'Student 1')]//td[@class='assessor_1']//select" to "marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 1')]//td[@class='assessor_2']//select" to "marker 2"
    And I set the field with xpath "//tr[contains(.,'Student 2')]//td[@class='assessor_1']//select" to "marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 2')]//td[@class='assessor_2']//select" to "marker 2"
    And I set the field with xpath "//tr[contains(.,'Student 3')]//td[@class='assessor_1']//select" to "marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 3')]//td[@class='assessor_2']//select" to "marker 2"
    And I press "Save"

    Then I should see "marker 1" in the "Student 1" "table_row"
    And I should see "marker 2" in the "Student 1" "table_row"
    And I should not see "marker 3" in the "Student 1" "table_row"
    And I should see "marker 1" in the "Student 2" "table_row"
    And I should see "marker 2" in the "Student 2" "table_row"
    And I should not see "marker 3" in the "Student 2" "table_row"
    And I should see "marker 1" in the "Student 3" "table_row"
    And I should see "marker 2" in the "Student 3" "table_row"
    And I should not see "marker 3" in the "Student 3" "table_row"

  Scenario: Check anonymity
    Given I am on the "Course 1" "course" page logged in as "admin"
    And I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
      | Formative or summative?                                     | Summative - counts towards the final module mark  |
      | Description                                                 | Test coursework description                       |
      | Display description on course page                          | Yes                                               |
      | Start date                                                  | ##now##                                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##                              |
      | Use marking deadline                                        | Yes                                               |
      | Types of file that students are allowed to submit           | pdf                                               |
      | Enable plagiarism flagging                                  | Yes                                               |
      | Number of times each submission should initially be marked. | 2                                                 |
      | Marker allocation enabled                                   | Yes                                               |
      | Marker allocation strategy                                  | Manual                                            |
      | Automatic agreement of marks                                | percentage distance                               |
      | Automatic agreement range                                   | 10                                                |
      | View initial markers' grades                                | No                                                |
      | Auto-populate agreed feedback comment                       | Yes                                               |
      | Blind marking                                               | Yes                                               |
      | Marker anonymity                                            | Yes                                               |

    Then I follow "Coursework – Double marking blind"
    And I follow "Add markers"
    And I follow "courseworkmarker"

    And I set the field "Potential users" to "marker 1 (marker1@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 2 (marker2@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 3 (marker3@example.com)"
    And I press "Add"

    Then I follow "Allocate markers"
    And I set the field "Allocation strategy" to "Manual"
    And I press "Apply"
    Then I should see "Please make sure markers are allocated"

    Then I set the field with xpath "//tr[contains(.,'Student 1')]//td[@class='assessor_1']//select" to "marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 1')]//td[@class='assessor_2']//select" to "marker 2"
    And I set the field with xpath "//tr[contains(.,'Student 2')]//td[@class='assessor_1']//select" to "marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 2')]//td[@class='assessor_2']//select" to "marker 2"
    And I set the field with xpath "//tr[contains(.,'Student 3')]//td[@class='assessor_1']//select" to "marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 3')]//td[@class='assessor_2']//select" to "marker 2"
    And I press "Save"

    And I log out
    And I am on the "Course 1" "course" page logged in as "marker1"
    And I follow "Coursework – Double marking blind"
    Then I should see "Submissions"
    And I should see "Hidden"
    And I should not see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"
