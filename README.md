# Dataholics Resolve

Dataholics Resolve is a multi-tenant support application for handling client service requests in a controlled and role-based workflow.

## Application Scope

The platform is focused on operational support management between Dataholics teams and client companies. It covers:

- Ticket intake, tracking, and follow-up
- Communication between clients and support staff
- Company-based data segregation (tenant isolation)
- Internal administration of companies, users, and roles
- Internal knowledge base management for support operations

## What the Application Does (High-Level)

Dataholics Resolve provides a complete support flow from request creation to closure:

1. A user logs in and is routed based on their role.
2. Client users create and monitor their own tickets.
3. ServiceDesk users work ticket queues, update status/priority, and respond.
4. Admin users manage companies, users, and platform settings.
5. Internal teams can use the knowledge base and agent definitions to standardize support responses.

## Main Functional Areas

- Authentication and session-based access control
- Client portal (company-branded where applicable)
- ServiceDesk dashboard for ticket operations
- Admin panel for companies and user lifecycle management
- Knowledge base module for internal documentation
- Agent definitions viewer for operational guidance

## User Roles

- client: End customer user who creates and follows own tickets
- client_admin: Customer account manager role
- servicedesk: Internal support operator
- admin: Platform administrator

## Architecture Overview

- Frontend: Static web pages under public_html
- Backend: API built with CodeIgniter 4 under api
- Database: Relational model with users, companies, tickets, and comments
- Security model: Session authentication + role checks + tenant isolation rules

## Business Goal

Provide a single, reliable support platform where Dataholics and each client company can collaborate efficiently while keeping tenant data isolated and operations auditable.
