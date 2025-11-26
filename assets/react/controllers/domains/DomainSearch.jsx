import React, { useState, useCallback } from 'react';
import * as Dialog from '@radix-ui/react-dialog';

export default function DomainSearch({ searchUrl, purchaseUrl, userEmail, isConfigured }) {
    const [keyword, setKeyword] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [searched, setSearched] = useState(false);
    const [isDemo, setIsDemo] = useState(false);
    const [selectedDomain, setSelectedDomain] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);

    const search = useCallback(async () => {
        if (!keyword || keyword.length < 2) return;

        setLoading(true);
        setError(null);
        setSearched(true);
        setResults([]);
        setIsDemo(false);

        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(keyword)}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response');
            }

            const data = await response.json();

            if (data.error) {
                setError(data.error);
            } else {
                setResults(data.results || []);
                setIsDemo(data.demo || false);
            }
        } catch (e) {
            console.error('Search error:', e);
            setError('Search failed: ' + e.message);
        } finally {
            setLoading(false);
        }
    }, [keyword, searchUrl]);

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            search();
        }
    };

    const selectDomain = (result) => {
        setSelectedDomain(result);
        setModalOpen(true);
    };

    return (
        <div className="space-y-6">
            {/* Search Box */}
            <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-8">
                <div className="text-center mb-8">
                    <h2 className="text-2xl font-bold text-white mb-2">Find Your Perfect Domain</h2>
                    <p className="text-gray-400">Search for available domain names</p>
                </div>

                <div className="max-w-2xl mx-auto">
                    <div className="relative">
                        <input
                            type="text"
                            value={keyword}
                            onChange={(e) => setKeyword(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder="Enter domain name or keyword..."
                            className="w-full px-6 py-4 bg-gray-900 border border-gray-700 rounded-xl text-white text-lg focus:outline-none focus:border-primary pr-32"
                        />
                        <button
                            onClick={search}
                            disabled={loading || !keyword}
                            className="absolute right-2 top-1/2 -translate-y-1/2 px-6 py-2 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all disabled:opacity-50"
                        >
                            {loading ? (
                                <span className="flex items-center gap-2">
                                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Searching...
                                </span>
                            ) : (
                                'Search'
                            )}
                        </button>
                    </div>

                    {/* Demo Mode Badge */}
                    {isDemo && (
                        <div className="mt-4 flex items-center justify-center gap-2 text-yellow-400 text-sm">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Demo mode - Results are simulated</span>
                        </div>
                    )}

                    {/* Error Message */}
                    {error && (
                        <div className="mt-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl">
                            <p className="text-red-400">{error}</p>
                        </div>
                    )}

                    {/* Search Results */}
                    {results.length > 0 && (
                        <div className="mt-6 space-y-3">
                            {results.map((result) => (
                                <div
                                    key={result.domain}
                                    className={`flex items-center justify-between p-4 bg-gray-900/50 border rounded-xl ${
                                        result.available
                                            ? 'border-green-500/30 bg-green-500/5'
                                            : 'border-red-500/30 bg-red-500/5 opacity-60'
                                    }`}
                                >
                                    <div className="flex items-center gap-4">
                                        <div
                                            className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                                                result.available ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                                            }`}
                                        >
                                            {result.available ? (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            ) : (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            )}
                                        </div>
                                        <div>
                                            <p className="font-mono font-medium text-white">{result.domain}</p>
                                            <p className={`text-sm ${result.available ? 'text-green-400' : 'text-red-400'}`}>
                                                {result.available ? 'Available' : 'Not Available'}
                                            </p>
                                        </div>
                                    </div>
                                    {result.available && (
                                        <div className="flex items-center gap-4">
                                            <div className="text-right">
                                                <p className="text-lg font-bold text-white">
                                                    ${result.pricing?.[0]?.price || 'N/A'}
                                                    <span className="text-sm text-gray-400">/yr</span>
                                                </p>
                                                {result.premium && (
                                                    <p className="text-xs text-yellow-400">Premium Domain</p>
                                                )}
                                            </div>
                                            <button
                                                onClick={() => selectDomain(result)}
                                                className="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-medium rounded-lg transition-all"
                                            >
                                                Register
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* No Results */}
                    {searched && results.length === 0 && !loading && !error && (
                        <div className="mt-6 text-center py-8">
                            <svg className="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <p className="text-gray-400">No domains found. Try a different search term.</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Registration Modal */}
            <Dialog.Root open={modalOpen} onOpenChange={setModalOpen}>
                <Dialog.Portal>
                    <Dialog.Overlay className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50" />
                    <Dialog.Content className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto">
                        <div className="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
                            <Dialog.Title className="text-lg font-semibold text-white">Register Domain</Dialog.Title>
                            <Dialog.Close className="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </Dialog.Close>
                        </div>

                        {selectedDomain && (
                            <form method="post" action={purchaseUrl} className="p-6 space-y-6">
                                <input type="hidden" name="domain" value={selectedDomain.domain} />

                                <div className="p-4 bg-gray-900/50 rounded-xl flex items-center justify-between">
                                    <div>
                                        <p className="font-mono text-xl font-bold text-white">{selectedDomain.domain}</p>
                                        <p className="text-sm text-green-400">Available</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-2xl font-bold text-white">
                                            ${selectedDomain.pricing?.[0]?.price || 'N/A'}
                                        </p>
                                        <p className="text-sm text-gray-400">per year</p>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">Registration Period</label>
                                    <select name="years" className="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white">
                                        <option value="1">1 Year</option>
                                        <option value="2">2 Years</option>
                                        <option value="3">3 Years</option>
                                        <option value="5">5 Years</option>
                                        <option value="10">10 Years</option>
                                    </select>
                                </div>

                                <div className="border-t border-gray-700 pt-6">
                                    <h4 className="font-medium text-white mb-4">Registrant Information</h4>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">First Name *</label>
                                            <input type="text" name="firstName" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">Last Name *</label>
                                            <input type="text" name="lastName" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div className="col-span-2">
                                            <label className="block text-sm text-gray-400 mb-1">Address *</label>
                                            <input type="text" name="address1" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">City *</label>
                                            <input type="text" name="city" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">State/Province *</label>
                                            <input type="text" name="state" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">Postal Code *</label>
                                            <input type="text" name="postalCode" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">Country *</label>
                                            <select name="country" required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white">
                                                <option value="US">United States</option>
                                                <option value="TR">Turkey</option>
                                                <option value="GB">United Kingdom</option>
                                                <option value="DE">Germany</option>
                                                <option value="FR">France</option>
                                                <option value="CA">Canada</option>
                                                <option value="AU">Australia</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">Phone *</label>
                                            <input type="tel" name="phone" required placeholder="+1.1234567890" className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                        <div>
                                            <label className="block text-sm text-gray-400 mb-1">Email *</label>
                                            <input type="email" name="email" defaultValue={userEmail} required className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white" />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end gap-3 pt-4 border-t border-gray-700">
                                    <Dialog.Close className="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg">
                                        Cancel
                                    </Dialog.Close>
                                    <button type="submit" className="px-6 py-3 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg">
                                        Complete Purchase
                                    </button>
                                </div>
                            </form>
                        )}
                    </Dialog.Content>
                </Dialog.Portal>
            </Dialog.Root>
        </div>
    );
}
