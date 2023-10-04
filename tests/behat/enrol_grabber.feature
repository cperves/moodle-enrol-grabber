@enrol @enrol_grabber
Feature: Tests multiple scenarios for enrol grabber

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | user1 | First | User | first@example.com |
      | user2 | Second | User | second@example.com |
    And the following course exists:
      | name      | Test course |
      | shortname | C1          |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | user1 | C1     | student |
      | user2 | C1     | student |

  @javascript
  Scenario: Tests without time limit and with it
    Given I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Enrolments grabber" "table_row"
    Then I am on the "C1" "enrolment methods" page
    And I should see "2" in the "Manual enrolments" "table_row"
    And I select "Enrolments grabber" from the "Add method" singleselect
    And I set the following fields to these values:
     | Custom instance name| Grabber 1 |
     | enrol instance to grab (same course) | Manual enrolments |
    And I press "Add method"
    Then I should see "0" in the "Manual enrolments" "table_row"
    And I should see "2" in the "Grabber 1" "table_row"