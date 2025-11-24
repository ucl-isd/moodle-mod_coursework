@mod @mod_coursework @double-marking-blind
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

  Scenario: Create coursework assignment with double marking
    Given there is a course
    And I am on the "Course 1" "course" page logged in as "admin"
    And I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
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
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | marker1   | C1     | teacher          |
      | marker2   | C1     | teacher          |
      | marker3   | C1     | teacher          |

    And I am on the "Course 1" "course" page logged in as "admin"
    And I follow "Coursework 1"
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
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | marker1   | C1     | teacher          |
      | marker2   | C1     | teacher          |
      | marker3   | C1     | teacher          |
      | student1  | C1     | student          |
      | student2  | C1     | student          |
      | student3  | C1     | student          |

    And I am on the "Course 1" "course" page logged in as "admin"
    And I follow "Coursework 1"
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
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | marker1   | C1     | teacher          |
      | marker2   | C1     | teacher          |
      | marker3   | C1     | teacher          |
      | student1  | C1     | student          |
      | student2  | C1     | student          |
      | student3  | C1     | student          |
    And I am on the "Course 1" "course" page logged in as "admin"

    Then I follow "Coursework 1"
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
    And I follow "Coursework 1"
    Then I should see "Submissions"
    And I should see "Hidden"
    And I should not see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"

  @javascript
  Scenario: Add extension to a student
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | marker1   | C1     | teacher          |
      | marker2   | C1     | teacher          |
      | marker3   | C1     | teacher          |
      | student1  | C1     | student          |
      | student2  | C1     | student          |
      | student3  | C1     | student          |
    And I am on the "Course 1" "course" page logged in as "admin"

    Then I follow "Coursework 1"
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

    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submission extension" "link"
    And I wait until the page is ready
    And I set the following fields to these values:
      | extended_deadline[day]    | 1       |
      | extended_deadline[month]  | January |
      | extended_deadline[year]   | 2027    |
      | extended_deadline[hour]   | 08      |
      | extended_deadline[minute] | 00      |
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    And I should see "1 January 2027, 8:00 AM" in the "Student 1" "table_row"
    Then I visit the coursework page
    And I should see "1 January 2027, 8:00 AM" in the "Student 1" "table_row"

  @javascript @_file_upload
  Scenario: Student can submit a PDF file
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | student1  | C1     | student          |

    When I am on the "Course 1" "course" page logged in as "student1"

    And I follow "Coursework 1"
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.pdf" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    And I should see the file on the page
    And I should see the edit submission button
    And I should see submission status "Submitted"
    And I should see submitted date "##today##%d %B %Y##"

  @javascript @_file_upload
  Scenario: Student with extension can submit after deadline w/o being late
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | student1  | C1     | student          |

    # The coursework deadline has passed
    Given the coursework deadline date is "##-5 minutes##"
    And the coursework extension for "Student 1" in "Coursework 1" is "## + 1 month ##"

    # Student has extension so late submission is in time
    When I am on the "Course 1" "course" page logged in as "student1"
    And I follow "Coursework 1"
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.pdf" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    And I should see the file on the page
    And I should see the edit submission button
    And I should see submission status "Submitted"
    And I should see submitted date "##today##%d %B %Y##"

  @javascript @_file_upload
  Scenario: Student has no extension so submission is late
    Given there is a course
    And there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | student1  | C1     | student          |

    # The coursework deadline has passed
    And the coursework deadline date is "##-5 minutes##"

    When I am on the "Course 1" "course" page logged in as "student1"
    And I follow "Coursework 1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.pdf" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    And I should see the file on the page
    And I should see submission status "Submitted"
    And I should see "late"
    And I should see late submitted date "##today##%d %B %Y##"
