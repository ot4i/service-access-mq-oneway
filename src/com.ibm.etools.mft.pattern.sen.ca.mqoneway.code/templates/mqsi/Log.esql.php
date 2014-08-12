BROKER SCHEMA mqsi

/**
 * Copyright (c) 2014 IBM Corporation and other Contributors
 *
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *     IBM - initial implementation
**/

DECLARE RequestLoggingOn EXTERNAL BOOLEAN <?php echo $_MB['PP']['pp9'] == 'true' ? 'true' : 'false' ?>;
DECLARE ResponseLoggingOn EXTERNAL BOOLEAN <?php echo $_MB['PP']['pp10'] == 'true' ? 'true' : 'false' ?>;

CREATE Compute MODULE CreateLogMessage
DECLARE MQMDRef REFERENCE TO OutputRoot.MQMD;
DECLARE outRef REFERENCE TO OutputRoot;
CREATE FUNCTION main() RETURNS BOOLEAN BEGIN

IF Environment.PatternVariables.RRMode = 'Response' THEN
	CALL LogResponse();
ELSE

		
	-- Create logging info in MQRFH2 - add to existing message
	SET OutputRoot = InputRoot;
	MOVE outRef TO OutputRoot.MQRFH2;
 	IF LASTMOVE(outRef) THEN
 	-- MQRFH2 EXISTS
    	SET outRef.usr.BrokerName = SQL.BrokerName;
    	SET outRef.usr.MessageFlowLabel = SQL.MessageFlowLabel; 
    	SET outRef.usr.DTSTAMP =   CURRENT_TIMESTAMP;
    	IF ResponseLoggingOn THEN SET outRef.usr.DTSTAMP = Environment.PatternVariables.ResponseTimestamp; END IF;
    	SET outRef.usr.RRMode =  Environment.PatternVariables.RRMode;
    	SET OutputRoot.MQMD.Format = MQFMT_RF_HEADER_2;
    
  
	 ELSE
	 -- CREATE THE MQRFH2 Header first
	
    	CREATE NEXTSIBLING OF OutputRoot.MQMD AS outRef DOMAIN('MQRFH2') NAME 'MQRFH2';
    	SET outRef.(MQRFH2.Field)Version = 2;  
    	SET outRef.usr.BrokerName = SQL.BrokerName;
    	SET outRef.usr.MessageFlowLabel = SQL.MessageFlowLabel; 
    	SET outRef.usr.DTSTAMP =   CURRENT_TIMESTAMP;
    	IF ResponseLoggingOn THEN SET outRef.usr.DTSTAMP = Environment.PatternVariables.ResponseTimestamp; END IF;
    	SET outRef.usr.RRMode =  Environment.PatternVariables.RRMode;
    	SET OutputRoot.MQMD.Format = MQFMT_RF_HEADER_2;

	END IF;
END IF;
END;

CREATE PROCEDURE LogResponse() BEGIN
	SET OutputRoot.Properties = NULL;
	-- No MQMD header so create domain 
	CREATE FIRSTCHILD OF OutputRoot AS MQMDRef DOMAIN ('MQMD') NAME 'MQMD';
	SET MQMDRef.Version = MQMD_CURRENT_VERSION;
	SET MQMDRef.ApplIdentityData = SQL.BrokerName;
	SET MQMDRef.CodedCharSetId = InputRoot.Properties.CodedCharSetId;
	SET MQMDRef.Encoding = InputRoot.Properties.Encoding;	
 -- CREATE THE MQRFH2 Header
    DECLARE MQMDRef REFERENCE TO OutputRoot.MQMD;	
    CREATE NEXTSIBLING OF MQMDRef AS outRef DOMAIN('MQRFH2') NAME 'MQRFH2';
    SET outRef.(MQRFH2.Field)Version = 2;  
    SET outRef.usr.BrokerName = SQL.BrokerName;
    SET outRef.usr.MessageFlowLabel = SQL.MessageFlowLabel; 
    SET outRef.usr.DTSTAMP =   CURRENT_TIMESTAMP;
    SET outRef.usr.RRMode =  Environment.PatternVariables.RRMode;
    SET outRef.usr.service = InputLocalEnvironment.WrittenDestination.SOAP.Request.WSA.To; 
    SET outRef.usr.Action = InputLocalEnvironment.WrittenDestination.SOAP.Request.WSA.Action;
    SET outRef.usr.URL = InputLocalEnvironment.WrittenDestination.SOAP.Request.Transport.HTTP.WebServiceURL;
    SET OutputRoot.MQMD.Format = MQFMT_RF_HEADER_2;
    CREATE NEXTSIBLING OF OutputRoot.MQRFH2 DOMAIN('XMLNSC') NAME 'XMLNSC';
	SET OutputRoot.XMLNSC = InputRoot.SOAP.Body;
	SET Environment.PatternVariables.ResponseTimestamp = outRef.usr.DTSTAMP;
	END;



END MODULE;
CREATE Compute MODULE CreateTraceData
CREATE FUNCTION main() RETURNS BOOLEAN BEGIN
	DECLARE EnvVarRef REFERENCE TO Environment;
	
	SET Environment.PatternVariables.Error.DTSTAMP = CURRENT_TIMESTAMP; 
	MOVE EnvVarRef TO Environment.PatternVariables.Error;
	SET EnvVarRef.RRMode = Environment.PatternVariables.RRMode;
	SET EnvVarRef.BrokerName = SQL.BrokerName;
    SET EnvVarRef.MessageFlowlabel = SQL.MessageFlowLabel;
    IF Environment.Patternvariables.RRMode = 'Response' THEN
    	SET EnvVarRef.service = InputLocalEnvironment.WrittenDestination.SOAP.Request.WSA.To; 
    	SET EnvVarRef.Action = InputLocalEnvironment.WrittenDestination.SOAP.Request.WSA.Action;
    	SET EnvVarRef.URL = InputLocalEnvironment.WrittenDestination.SOAP.Request.Transport.HTTP.WebServiceURL;
    END IF;
    SET EnvVarRef.Text = 'Failure writing to Log queue';

RETURN TRUE;
END;
END MODULE;

CREATE FILTER MODULE CheckLogging
CREATE FUNCTION main() RETURNS BOOLEAN BEGIN

	IF Environment.PatternVariables.RRMode = 'Response' THEN
		RETURN  ResponseLoggingOn;
	END IF;

	IF Environment.PatternVariables.RRMode = 'Request' THEN
		RETURN  RequestLoggingOn;
	END IF;
	RETURN FALSE;
	END;

END MODULE;

CREATE Compute MODULE SetResponseMode
CREATE FUNCTION main() RETURNS BOOLEAN BEGIN
	SET OutputRoot = InputRoot;
	SET Environment.PatternVariables.RRMode = 'Response'; 
RETURN TRUE;
END;
END MODULE;


CREATE Compute MODULE SetRequestMode
CREATE FUNCTION main() RETURNS BOOLEAN BEGIN
	SET OutputRoot = InputRoot;
	SET Environment.PatternVariables.RRMode = 'Request'; 
RETURN TRUE;
END;
END MODULE;