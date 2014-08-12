/*******************************************************************************
 * Copyright (c) 2014 IBM Corporation and other Contributors
 *
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *     IBM - initial implementation
 *******************************************************************************/
package com.ibm.etools.mft.pattern.sen.ca.mqoneway.code;

import com.ibm.broker.config.appdev.MessageFlow;
import com.ibm.broker.config.appdev.Node;
import com.ibm.broker.config.appdev.nodes.FilterNode;
import com.ibm.broker.config.appdev.nodes.FlowOrderNode;
import com.ibm.broker.config.appdev.nodes.ThrowNode;
import com.ibm.broker.config.appdev.patterns.GeneratePatternInstanceTransform;
import com.ibm.broker.config.appdev.patterns.PatternInstanceManager;


public class PatternGenerator implements GeneratePatternInstanceTransform {
	
	private static final String PROJECT_NAME = "ServiceAccessMQone-way";
	
	//Flows
	private static final String ACCESS_FLOW = "mqsi/Access.msgflow";
	private static final String LOG_FLOW = "mqsi/Log.subflow";
	private static final String ERROR_FLOW = "mqsi/Error.subflow";
	
	private static final String PROPERTY_REQUEST_LOGGING = "pp9";
	private static final String PROPERTY_RESPONSE_LOGGING = "pp10";

	private static final String PROPERTY_ERROR_NOTIFICATION = "pp12";
	private static final String PROPERTY_ERROR_ACTION = "pp11";
	
	private PatternInstanceManager patternInstanceManager;

	@Override
	public void onGeneratePatternInstance(PatternInstanceManager patternInstanceManager) {
		
		this.patternInstanceManager = patternInstanceManager;
		
		// The location for the generated projects 
		String location = patternInstanceManager.getWorkspaceLocation();
		
		// The pattern instance name for this generation
		String patternInstanceName = patternInstanceManager.getPatternInstanceName();
		
		Boolean requestLoggingRequired = patternInstanceManager.getParameterValue(PROPERTY_REQUEST_LOGGING).equals("true");
		Boolean responseLoggingRequired = patternInstanceManager.getParameterValue(PROPERTY_RESPONSE_LOGGING).equals("true");
		
		this.setUpErrorHandling();
		
		//Remove Log flow if Logging not required
		if (!requestLoggingRequired && !responseLoggingRequired) {
			MessageFlow logMsgFlow = this.patternInstanceManager.getMessageFlow(PROJECT_NAME, LOG_FLOW);
			
			if (logMsgFlow != null) {
				this.patternInstanceManager.removeMessageFlow(logMsgFlow);
				//Remove nodes
				MessageFlow accessFlow = this.patternInstanceManager.getMessageFlow(PROJECT_NAME, ACCESS_FLOW);
				accessFlow.removeNode(accessFlow.getNodeByName("Log"));
			}
		}	
		
	}

	private void setUpErrorHandling() {
		MessageFlow errorMsgFlow = this.patternInstanceManager.getMessageFlow(PROJECT_NAME, ERROR_FLOW);
		
		Boolean errorNotification = this.patternInstanceManager.getParameterValue(PROPERTY_ERROR_NOTIFICATION).equals("true");
		Boolean errorQueue = this.patternInstanceManager.getParameterValue(PROPERTY_ERROR_ACTION).equals("error_queue");
		Boolean rollBack = this.patternInstanceManager.getParameterValue(PROPERTY_ERROR_ACTION).equals("roll_back");
		
		if (errorMsgFlow != null) {
			if (!errorNotification) {
				//Remove error notification nodes
				Node filterNode = errorMsgFlow.getNodeByName("Notify if Required");
				Node computerNode = errorMsgFlow.getNodeByName("Build Notification");
				Node mqInputNode = errorMsgFlow.getNodeByName("Write Notification");
				
				errorMsgFlow.removeNode(filterNode);
				errorMsgFlow.removeNode(computerNode);
				errorMsgFlow.removeNode(mqInputNode);
			}
			
			ThrowNode rollBackNode = (ThrowNode) errorMsgFlow.getNodeByName("Rollback");

			FlowOrderNode notificationFirstNode = (FlowOrderNode) errorMsgFlow.getNodeByName("Notification First");
	
			if (rollBack) {
				
				//Remove error queue stuff
				errorMsgFlow.removeNode(errorMsgFlow.getNodeByName("Test Action"));
				errorMsgFlow.removeNode(errorMsgFlow.getNodeByName("Set Properties"));
				errorMsgFlow.removeNode(errorMsgFlow.getNodeByName("ErrorOutput"));
				
				errorMsgFlow.connect( notificationFirstNode.OUTPUT_TERMINAL_SECOND, rollBackNode.INPUT_TERMINAL_IN);
				
			} else if (errorQueue) {
				FilterNode filterNode = (FilterNode) errorMsgFlow.getNodeByName("Test Action");
				errorMsgFlow.connect( notificationFirstNode.OUTPUT_TERMINAL_SECOND, filterNode.INPUT_TERMINAL_IN);
			}
		} 
	}


}
