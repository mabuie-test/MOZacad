ALTER TABLE generated_documents
  ADD COLUMN template_application_json JSON NULL AFTER version;
