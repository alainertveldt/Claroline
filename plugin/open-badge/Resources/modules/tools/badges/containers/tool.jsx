import {connect} from 'react-redux'

import {selectors as toolSelectors} from '#/main/core/tool/store'

import {BadgeTool as BadgeToolComponent} from '#/plugin/open-badge/tools/badges/components/tool'
import {actions, selectors} from '#/plugin/open-badge/tools/badges/store'

const BadgeTool = connect(
  (state) => ({
    currentContext: toolSelectors.context(state)
  }),
  dispatch => ({
    openBadge(id = null, workspace = null) {
      dispatch(actions.openBadge(selectors.STORE_NAME +'.badges.current', id, workspace))
    },
    openAssertion(id) {
      dispatch(actions.openAssertion(selectors.STORE_NAME +'.badges.assertion', id))
    }
  })
)(BadgeToolComponent)

export {
  BadgeTool
}