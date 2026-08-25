import Link from 'next/link';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import LandingClient from './LandingClient';

export default async function LandingPage() {
  return <LandingClient />;
}
