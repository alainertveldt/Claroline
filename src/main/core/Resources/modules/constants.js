
// evaluation
const EVALUATION_STATUS_NOT_ATTEMPTED = 'not_attempted'
const EVALUATION_STATUS_TODO          = 'todo'
const EVALUATION_STATUS_UNKNOWN       = 'unknown'
const EVALUATION_STATUS_OPENED        = 'opened'
const EVALUATION_STATUS_INCOMPLETE    = 'incomplete'
const EVALUATION_STATUS_PARTICIPATED  = 'participated'
const EVALUATION_STATUS_FAILED        = 'failed'
const EVALUATION_STATUS_COMPLETED     = 'completed'
const EVALUATION_STATUS_PASSED        = 'passed'

const EVALUATION_STATUS_PRIORITY = {
  [EVALUATION_STATUS_NOT_ATTEMPTED]: 0,
  [EVALUATION_STATUS_TODO]:          0,
  [EVALUATION_STATUS_UNKNOWN]:       1,
  [EVALUATION_STATUS_OPENED]:        2,
  [EVALUATION_STATUS_INCOMPLETE]:    3,
  [EVALUATION_STATUS_PARTICIPATED]:  4,
  [EVALUATION_STATUS_FAILED]:        5,
  [EVALUATION_STATUS_COMPLETED]:     6,
  [EVALUATION_STATUS_PASSED]:        7
}

export const constants = {
  // evaluation
  EVALUATION_STATUS_PRIORITY,

  EVALUATION_STATUS_NOT_ATTEMPTED,
  EVALUATION_STATUS_TODO,
  EVALUATION_STATUS_UNKNOWN,
  EVALUATION_STATUS_OPENED,
  EVALUATION_STATUS_INCOMPLETE,
  EVALUATION_STATUS_PARTICIPATED,
  EVALUATION_STATUS_COMPLETED,
  EVALUATION_STATUS_PASSED,
  EVALUATION_STATUS_FAILED
}