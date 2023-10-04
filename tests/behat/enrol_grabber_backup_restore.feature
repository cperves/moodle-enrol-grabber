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
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Enrolments grabber" "table_row"
    Then I am on the "C1" "enrolment methods" page
    And I should see "2" in the "Manual enrolments" "table_row"
    And I select "Enrolments grabber" from the "Add method" singleselect
    And I set the following fields to these values:
      | name| Grabber 1 |
      | customint1 | Manual enrolments |
    And I press "Add method"
    Then I should see "0" in the "Manual enrolments" "table_row"
    And I should see "2" in the "Grabber 1" "table_row"
    And I backup "C1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
      | Initial |  Include enrolled users | 1 |

  @javascript
  Scenario: restore with user datas and include enrol method
    When I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | C2 |
      | Settings | Include enrolled users | 1 |
      | Settings | Include user role assignments | 1 |
      | Settings | Include enrolment methods | 1 |
    Then I am on the "C2 copy 1" "enrolment methods" page
    Then I should see "Grabber 1"
    Then I should see "2" in the "Grabber 1" "table_row"

  @javascript
  Scenario: restore without user datas and include enrol method
    When I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | C2 |
      | Settings | Include enrolled users | 0 |
      | Settings | Include user role assignments | 0 |
      | Settings | Include enrolment methods | 2 |
    Then I am on the "C2 copy 1" "enrolment methods" page
    Then I should see "Grabber 1"
    Then I should see "0" in the "Grabber 1" "table_row"

  @javascript
  Scenario: restore without user datas and without enrol method
    When I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | C2 |
      | Settings | Include enrolled users | 0 |
      | Settings | Include user role assignments | 0 |
      | Settings | Include enrolment methods | 0 |
    Then I am on the "C2 copy 1" "enrolment methods" page
    Then I should not see "Grabber 1"

  @javascript
  Scenario: restore with user datas and without enrol method
    When I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | C2 |
      | Settings | Include enrolled users | 1 |
      | Settings | Include user role assignments | 1 |
      | Settings | Include enrolment methods | 0 |
    Then I am on the "C2 copy 1" "enrolment methods" page
    Then I should not see "Grabber 1"
    Then I should see "2" in the "Manual enrolments" "table_row"


