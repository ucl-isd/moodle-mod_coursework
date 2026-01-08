@mod @mod_coursework @double-marking-blind
Feature: Double marking - blind
  In order to ensure double marking works correctly
  As an admin
  I want to perform the full coursework workflow with blind marking.

  Background:
    And the following "roles" exist:
      | shortname             | name                  | archetype |
      | courseworkexamoffice  | courseworkexamoffice  |           |
      | courseworkdbm         | courseworkdbm         |           |
      | courseworkmoderator   | courseworkmoderator   |           |
      | norole                | norole                |           |

    And the following "role capability" exists:
      | role                                          | courseworkexamoffice  |
      | mod/coursework:addinstance                    | allow                 |
      | moodle/role:assign                            | allow                 |
      | mod/coursework:addagreedgrade                 | allow                 |
      | mod/coursework:addallocatedagreedgrade        | allow                 |
      | mod/coursework:addgeneralfeedback             | allow                 |
      | mod/coursework:addinitialgrade                | allow                 |
      | mod/coursework:addplagiarismflag              | allow                 |
      | mod/coursework:administergrades               | allow                 |
      | mod/coursework:allocate                       | allow                 |
      | mod/coursework:canexportfinalgrades           | allow                 |
      | mod/coursework:editagreedgrade                | allow                 |
      | mod/coursework:editallocatedagreedgrade       | allow                 |
      | mod/coursework:editinitialgrade               | allow                 |
      | mod/coursework:editpersonaldeadline           | allow                 |
      | mod/coursework:grade                          | allow                 |
      | mod/coursework:grantextensions                | allow                 |
      | mod/coursework:moderate                       | allow                 |
      | mod/coursework:publish                        | allow                 |
      | mod/coursework:receivesubmissionnotifications | allow                 |
      | mod/coursework:revertfinalised                | allow                 |
      | mod/coursework:submitonbehalfof               | allow                 |
      | mod/coursework:updateplagiarismflag           | allow                 |
      | mod/coursework:view                           | allow                 |
      | mod/coursework:viewallgradesatalltimes        | allow                 |
      | mod/coursework:viewallstudents                | allow                 |
      | mod/coursework:viewanonymous                  | allow                 |
      | mod/coursework:viewextensions                 | allow                 |
      | moodle/course:manageactivities                | allow                 |
      | moodle/calendar:manageentries                 | allow                 |

    And the following "role capability" exists:
      | role                                          | courseworkdbm |
      | mod/coursework:addallocatedagreedgrade        | allow         |
      | mod/coursework:addgeneralfeedback             | allow         |
      | mod/coursework:addinitialgrade                | allow         |
      | mod/coursework:addplagiarismflag              | allow         |
      | mod/coursework:editagreedgrade                | allow         |
      | mod/coursework:editallocatedagreedgrade       | allow         |
      | mod/coursework:editinitialgrade               | allow         |
      | mod/coursework:grade                          | allow         |
      | mod/coursework:receivesubmissionnotifications | allow         |
      | mod/coursework:updateplagiarismflag           | allow         |
      | mod/coursework:view                           | allow         |
      | mod/coursework:viewextensions                 | allow         |

    And the following "role capability" exists:
      | role                                          | courseworkmoderator |
      | mod/coursework:addplagiarismflag              | allow               |
      | mod/coursework:grade                          | allow               |
      | mod/coursework:moderate                       | allow               |
      | mod/coursework:updateplagiarismflag           | allow               |
      | mod/coursework:view                           | allow               |
      | mod/coursework:viewallgradesatalltimes        | allow               |
      | mod/coursework:viewextensions                 | allow               |

    And the role "courseworkexamoffice" is allowed to assign role "courseworkmoderator"
    And the role "courseworkexamoffice" is allowed to assign role "courseworkdbm"

    And the following "users" exist:
      | username    | firstname   | lastname | email                  |
      | manager     | Assessment  | Manager  | manager@example.com    |
      | marker1     | Marker      | 1        | marker1@example.com    |
      | marker2     | Marker      | 2        | marker2@example.com    |
      | marker3     | Marker      | 3        | marker3@example.com    |
      | moderator1  | Moderator   | 1        | moderator1@example.com |
      | student1    | Student     | 1        | student1@example.com   |
      | student2    | Student     | 2        | student2@example.com   |
      | student3    | Student     | 3        | student3@example.com   |

    Given there is a course
    And the following "course enrolments" exist:
      | user      | course | role                 |
      | manager   | C1     | courseworkexamoffice |

  Scenario: Create coursework assignment with double blind marking
    Given I am on the "Course 1" "course" page logged in as "manager"
    And I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind |
      | Description                                                 | Test coursework description       |
      | Display description on course page                          | Yes                               |
      | Start date                                                  | ##now##                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##              |
      | Use marking deadline                                        | Yes                               |
      | Types of file that students are allowed to submit           | pdf                               |
      | Enable plagiarism flagging                                  | Yes                               |
      | Number of times each submission should initially be marked. | 2                                 |
      | Marker allocation enabled                                   | Yes                               |
      | Marker allocation strategy                                  | Manual                            |
      | Automatic agreement of marks                                | Percentage distance               |
      | Automatic agreement range                                   | 10                                |
      | View initial markers' grades                                | No                                |
      | Auto-populate agreed feedback comment                       | Yes                               |
      | Blind marking                                               | Yes                               |
      | Marker anonymity                                            | Yes                               |

    Then I should see "Coursework – Double marking blind"

  Scenario: Allocate markers
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role           |
      | marker1   | C1     | courseworkdbm  |
      | marker2   | C1     | courseworkdbm  |
      | marker3   | C1     | courseworkdbm  |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
      | student3  | C1     | student        |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I follow "Allocate markers"
    And I set the field "Allocation strategy" to "Manual"
    And I press "Apply"
    Then I should see "Please make sure markers are allocated"

    When I set the field with xpath "//tr[contains(.,'Student 1')]//td[@class='assessor_1']//select" to "Marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 1')]//td[@class='assessor_2']//select" to "Marker 2"
    And I set the field with xpath "//tr[contains(.,'Student 2')]//td[@class='assessor_1']//select" to "Marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 2')]//td[@class='assessor_2']//select" to "Marker 2"
    And I set the field with xpath "//tr[contains(.,'Student 3')]//td[@class='assessor_1']//select" to "Marker 1"
    And I set the field with xpath "//tr[contains(.,'Student 3')]//td[@class='assessor_2']//select" to "Marker 2"
    And I press "Save"

    Then I should see "Marker 1" in the "Student 1" "table_row"
    And I should see "Marker 2" in the "Student 1" "table_row"
    And I should not see "Marker 3" in the "Student 1" "table_row"
    And I should see "Marker 1" in the "Student 2" "table_row"
    And I should see "Marker 2" in the "Student 2" "table_row"
    And I should not see "Marker 3" in the "Student 2" "table_row"
    And I should see "Marker 1" in the "Student 3" "table_row"
    And I should see "Marker 2" in the "Student 3" "table_row"
    And I should not see "Marker 3" in the "Student 3" "table_row"

  Scenario: Check anonymity
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role           |
      | marker1   | C1     | courseworkdbm  |
      | marker2   | C1     | courseworkdbm  |
      | marker3   | C1     | courseworkdbm  |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
      | student3  | C1     | student        |
    And I am on the "Course 1" "course" page logged in as "manager"

    And I allocate the following markers:
      | student   | assessor_1 | assessor_2 |
      | Student 1 | Marker 1   | Marker 2   |
      | Student 2 | Marker 1   | Marker 2   |
      | Student 3 | Marker 1   | Marker 2   |

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
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role           |
      | marker1   | C1     | courseworkdbm  |
      | marker2   | C1     | courseworkdbm  |
      | marker3   | C1     | courseworkdbm  |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
      | student3  | C1     | student        |
    And I am on the "Course 1" "course" page logged in as "manager"

    And I allocate the following markers:
      | student   | assessor_1 | assessor_2 |
      | Student 1 | Marker 1   | Marker 2   |
      | Student 2 | Marker 1   | Marker 2   |
      | Student 3 | Marker 1   | Marker 2   |

    And I follow "Coursework 1"
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submission extension" "link"
    And I wait until the page is ready
    And I set the following fields to these values:
      | extended_deadline[day]    | ##tomorrow##%d##  |
      | extended_deadline[month]  | ##tomorrow##%B##  |
      | extended_deadline[year]   | ##tomorrow##%Y##  |
      | extended_deadline[hour]   | 08                |
      | extended_deadline[minute] | 00                |
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    And I should see "##tomorrow##%d %B %Y, 8:00 AM##" in the "Student 1" "table_row"
    Then I visit the coursework page
    And I should see "##tomorrow##%d %B %Y, 8:00 AM##" in the "Student 1" "table_row"

  @javascript @_file_upload
  Scenario: Student can submit a PDF file
    Given there is a double-blind marking coursework
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
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | student1  | C1     | student          |

    # The coursework deadline has passed 5 minutes ago
    Given the coursework deadline date is "##-5 minutes##"

    # Student has extension so late submission is in time
    And the coursework extension for "Student 1" in "Coursework 1" is "## + 1 month ##"
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
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role             |
      | student1  | C1     | student          |

    # The coursework deadline has passed 5 minutes ago
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

  @javascript @_file_upload
  Scenario: Manager can submit on behalf of students.
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role           |
      | marker1   | C1     | courseworkdbm  |
      | marker2   | C1     | courseworkdbm  |
      | marker3   | C1     | courseworkdbm  |
      | student1  | C1     | student        |

    And the student called "Student 1" has a finalised submission

    When I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    Then I should see "Add mark"

    # Unfinalise a submission.
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Unfinalise submission" "link"
    And I wait until the page is ready
    Then I should see "Are you sure you want to unfinalise the submission"
    And I press "Yes"
    Then I should not see "Agree marking"

    # Submit on behalf.
    When I press "Actions"
    And I wait until the page is ready
    And I click on "Edit submission on behalf of this student" "link"
    And I wait until the page is ready
    Then I should see "Edit your submission"
    And I should see "myfile.txt"
    And I follow "myfile.txt"
    And I wait until "Delete" "button" exists
    # Delete previous file
    # And I click on "Delete" "button" - does not work
    And I click on "//div[contains(@class,'fp-select')]//button[contains(@class,'fp-file-delete')]" "xpath"
    Then I should see "Are you sure you want to delete this file?"
    And I press "Yes"
    # Now upload a new file
    And I upload "mod/coursework/tests/files_for_uploading/TestPDF1.pdf" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    And I should not see "myfile.txt"

  @javascript @_file_upload
  Scenario: Mark the assignments
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role           |
      | marker1   | C1     | courseworkdbm  |
      | marker2   | C1     | courseworkdbm  |
      | marker3   | C1     | courseworkdbm  |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
      | student3  | C1     | student        |

    And I am on the "Course 1" "course" page logged in as "manager"

    And I allocate the following markers:
      | student   | assessor_1 | assessor_2 |
      | Student 1 | Marker 1   | Marker 2   |
      | Student 2 | Marker 1   | Marker 2   |
      | Student 3 | Marker 1   | Marker 2   |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And I log out
    And I am on the "Course 1" "course" page logged in as "marker1"
    And I follow "Coursework 1"

    And I click on "Add mark" "link" in the "(//tr[contains(@class,'mod-coursework-submissions-row')])[1]" "xpath_element"

    Then I should see "Marking for Hidden"
    When I set the following fields to these values:
      | Mark    | 71              |
      | Comment | Test comment 1  |
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.pdf" file to "Upload a file" filemanager
    And I press "Save as draft"

    And I click on "Add mark" "link" in the "(//tr[contains(@class,'mod-coursework-submissions-row')])[2]" "xpath_element"

    Then I should see "Marking for Hidden"
    When I set the following fields to these values:
      | Mark    | 72              |
      | Comment | Test comment 2  |
    And I press "Save as draft"

    And I click on "Add mark" "link" in the "(//tr[contains(@class,'mod-coursework-submissions-row')])[3]" "xpath_element"
    Then I should see "Marking for Hidden"
    When I set the following fields to these values:
      | Mark    | 73              |
      | Comment | Test comment 3  |
    And I press "Save as draft"

    Then I should see "Submissions"
    And "71" "link" should exist
    And "72" "link" should exist
    And "73" "link" should exist

  @javascript
  Scenario: Verify assignment shows in marking
    Given there is a double-blind marking coursework
    And the following "course enrolments" exist:
      | user      | course | role           |
      | marker1   | C1     | courseworkdbm  |
      | marker2   | C1     | courseworkdbm  |
      | student1  | C1     | student        |

    And I allocate the following markers:
      | student   | assessor_1 | assessor_2 |
      | Student 1 | Marker 1   | Marker 2   |

    And the student called "Student 1" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark    | 70              |
      | Comment | Excellent work! |

    And the submission from "Student 1" is marked by "Marker 2" with:
      | Mark    | 65    |
      | Comment | Nice! |

    And I am on the "Course 1" "course" page logged in as "student1"
    And I follow "Coursework 1"
    And I wait until the page is ready
    Then I should see "Submission"
    And I should see "In marking"
    And I should not see "Edit your submission"

  Scenario: Moderate the assessment
    Given there is a blind marking moderated coursework
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | moderator1  | C1     | courseworkmoderator  |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    And I allocate the following markers:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And I am on the "Course 1" "course" page logged in as "moderator1"
    And I follow "Coursework 1"
    And I follow "Agree marking" in row "1"
    Then I should see "Moderation for"
    And I set the field "Moderation agreement" to "Agreed"
    And I press "Save changes"
    And I follow "Agree marking" in row "2"
    Then I should see "Moderation for"
    And I set the field "Moderation agreement" to "Disagreed"
    And I set the field "Comment" to "I don't like it!"
    And I press "Save changes"
    Then I should see "Agreed" in row "1"
    Then I should see "Disagreed" in row "2"
    When I follow "Disagreed"
    And I wait until the page is ready
    Then I should see "I don't like it!"

  Scenario: Check moderation
    Given there is a blind marking moderated coursework
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | moderator1  | C1     | courseworkmoderator  |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    And I allocate the following markers:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "marker1"
    And I follow "Coursework 1"
    Then I should see "Agreed" in row "1"
    Then I should see "Disagreed" in row "2"

    # See the moderation
    And I follow "Disagreed" in row "2"
    And I wait until the page is ready
    Then I should see "I don't like it at all!"
    And I press "Cancel"

    # Update marking
    And I follow "75" in row "2"
    When I set the following fields to these values:
      | Mark    | 65              |
      | Comment | Updated mark    |
    And I press "Save as draft"
    Then I should see "65" in row "2"

  @javascript
  Scenario: Release the grades
    Given there is a blind marking moderated coursework
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | moderator1  | C1     | courseworkmoderator  |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    And I allocate the following markers:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    Then I should see "Agreed" in row "1"
    Then I should see "Disagreed" in row "2"

    When I follow "Release the marks"
    Then I should see "Are you sure you want to release all marks?"
    And I press "Confirm"
    Then I should see "Marks released"
    And I should see "Released" in row "1"
    And I should see "Released" in row "2"
    And I should see "Released" in row "3"

  @javascript
  Scenario: Student 1 sees the released grades
    Given there is a blind marking moderated coursework
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | moderator1  | C1     | courseworkmoderator  |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    And I allocate the following markers:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I press the release marks button

    And I log out

    And I am on the "Course 1" "course" page logged in as "student1"
    And I follow "Coursework 1"
    Then I should see "Agreed feedback for Student 1"
    And I should see "Marker 1"
    And I should see "Excellent work!"
    And I should see "70"
    And I should see "Released"

  @javascript
  Scenario: Student 2 sees disagreed released grades
    Given there is a blind marking moderated coursework
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | moderator1  | C1     | courseworkmoderator  |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    And I allocate the following markers:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I press the release marks button
    Then I should see "Disagreed" in row "2"
    And I should see "Released" in row "2"

    And I log out

    # Student can see released feedback even its was not agreed on.
    And I am on the "Course 1" "course" page logged in as "student2"
    And I follow "Coursework 1"
    Then I should see "Agreed feedback for Student 2"
    And I should see "Marker 1"
    And I should see "Superb work!"
    And I should see "75"
    And I should see "Released"

  @javascript
  Scenario: Check moderation form
    Given there is a blind marking moderated coursework
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | moderator1  | C1     | courseworkmoderator  |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    And I allocate the following markers:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I press the release marks button
    And I log out

    # Student cannot see released feedback as moderation is still missing.
    And I am on the "Course 1" "course" page logged in as "moderator1"
    And I follow "Coursework 1"
    And I follow "Agreed" in row "1"
    Then I should see "Moderation for "
    And I should see "Moderator 1"
    And "Save changes" "button" should not exist
